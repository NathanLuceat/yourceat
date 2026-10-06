<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebbliotecaService
{
    public function recentActivities(int $limit = 10): array
    {
        try {
            return Cache::remember('webblioteca:activities', 30, fn () =>
                Http::baseUrl(config('services.webblioteca.url'))
                    ->withToken(config('services.webblioteca.token'))
                    ->acceptJson()->timeout(5)
                    ->get('/api/v1/activities', ['limit' => $limit])
                    ->throw()->json('data') ?? []
            );
        } catch (\Throwable $e) {
            Log::warning('Webblioteca API falhou', ['erro' => $e->getMessage()]);
            return [];
        }
    }
}