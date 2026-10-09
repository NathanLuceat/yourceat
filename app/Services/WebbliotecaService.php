<?php

namespace App\Services;

use App\Models\WebbliotecaActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use UnexpectedValueException;

class WebbliotecaService
{
    /**
     * Importa um lote por janela; chamadas seguintes continuam o backlog.
     *
     * @return array{status: 'synced'|'skipped'|'failed', count: int}
     */
    public function sync(): array
    {
        $stage = 'configuration';
        $status = null;
        $lock = null;
        $acquired = false;

        try {
            $url = config('services.webblioteca.url');
            $token = config('services.webblioteca.token');
            $token = is_string($token) ? trim($token) : '';
            $token = preg_replace('/^Bearer\s+/i', '', $token) ?? '';

            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
                || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
                || parse_url($url, PHP_URL_USER) !== null || $token === '') {
                throw new UnexpectedValueException('Invalid configuration');
            }

            $stage = 'storage';
            $lock = Cache::lock('webblioteca:sync:lock', 60);
            $acquired = $lock->get();
            if (! $acquired || ! Cache::add('webblioteca:sync', true, 20)) {
                return ['status' => 'skipped', 'count' => 0];
            }

            $sinceId = (string) (WebbliotecaActivity::max('external_id') ?? '0');
            $stage = 'request';
            $response = Http::baseUrl(rtrim($url, '/'))
                ->withToken($token)
                ->acceptJson()
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout(10)
                ->get('/api/v1/activities', ['since_id' => $sinceId, 'limit' => 50]);
            $status = $response->status();

            if (! $response->successful()) {
                throw new UnexpectedValueException('Unsuccessful response');
            }

            $stage = 'payload';
            $payload = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            if (! is_array($payload) || ! isset($payload['data'])
                || ! is_array($payload['data']) || ! array_is_list($payload['data'])
                || count($payload['data']) > 50) {
                throw new UnexpectedValueException('Invalid envelope');
            }

            $rows = [];
            $previousId = $sinceId;
            foreach ($payload['data'] as $item) {
                if (! is_array($item)) {
                    throw new UnexpectedValueException('Invalid activity');
                }

                Validator::make($item, [
                    'id' => ['required', 'integer', 'min:1'],
                    'type' => ['required', 'string', 'max:255'],
                    'summary' => ['present', 'nullable', 'array'],
                    'actor' => ['required', 'array'],
                    'actor.id' => ['present', 'nullable', 'integer', 'min:1'],
                    'created_at' => ['required', 'string', 'date'],
                ])->validate();

                // O upstream valida since_id como inteiro PHP; nunca converter para float.
                if ((! is_int($item['id']) && ! is_string($item['id']))
                    || (int) $item['id'] <= (int) $previousId) {
                    throw new UnexpectedValueException('Invalid activity order');
                }

                $previousId = (string) $item['id'];
                $rows[] = [
                    'external_id' => $previousId,
                    'type' => $item['type'],
                    'summary' => $item['summary'] ?? [],
                    'is_system' => $item['actor']['id'] === null,
                    'occurred_at' => Carbon::parse($item['created_at'])->setTimezone(config('app.timezone')),
                ];
            }

            $stage = 'storage';
            // Um lote inválido ou incompleto não pode avançar o cursor local.
            DB::transaction(function () use ($rows) {
                foreach ($rows as $row) {
                    $id = $row['external_id'];
                    unset($row['external_id']);
                    WebbliotecaActivity::updateOrCreate(['external_id' => $id], $row);
                }
            });

            return ['status' => 'synced', 'count' => count($rows)];
        } catch (\Throwable $e) {
            // Mensagens de exceção HTTP podem conter corpo da resposta e dados pessoais.
            Log::warning('Webblioteca sync failed', ['stage' => $stage, 'http_status' => $status]);

            return ['status' => 'failed', 'count' => 0];
        } finally {
            if ($acquired) {
                try {
                    // release() verifica o owner, inclusive após expiração e nova aquisição.
                    $lock->release();
                } catch (\Throwable $e) {
                    Log::warning('Webblioteca sync failed', ['stage' => 'lock_release', 'http_status' => null]);
                }
            }
        }
    }
}
