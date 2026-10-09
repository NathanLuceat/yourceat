<?php

namespace App\Http\Controllers;

use App\Support\GitHubCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use JsonException;

class GitHubWebhookController extends Controller
{
    public function __invoke(Request $request, GitHubCache $cache): JsonResponse
    {
        $rateKey = 'github-webhook:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($rateKey, 60)) {
            return response()->json(['error' => 'Too many requests'], 429);
        }
        RateLimiter::hit($rateKey, 60);
        $body = $request->getContent();
        if (strlen($body) > 1048576) {
            return response()->json(['error' => 'Payload too large'], 413);
        }
        $secret = config('services.github.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json(['error' => 'Webhook unavailable'], 503);
        }
        $signature = $request->header('X-Hub-Signature-256', '');
        if (! preg_match('/\Asha256=[a-f0-9]{64}\z/', $signature)
            || ! hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        try {
            $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }
        if (! is_array($payload) || ! str_starts_with(ltrim($body), '{')) {
            return response()->json(['error' => 'Invalid payload'], 422);
        }
        $repo = $payload['repository'] ?? null;
        $owner = config('services.github.username');
        if (! is_array($repo) || ! is_string($owner) || ! is_array($repo['owner'] ?? null)
            || ! is_string($repo['owner']['login'] ?? null)
            || strcasecmp($repo['owner']['login'], $owner) !== 0
            || ! $this->validName($repo['name'] ?? null)
            || ! is_string($repo['full_name'] ?? null)
            || strcasecmp($repo['full_name'], $owner.'/'.$repo['name']) !== 0) {
            return response()->json(['error' => 'Repository not authorized'], 403);
        }
        $event = $request->header('X-GitHub-Event');
        $action = $payload['action'] ?? null;
        $identity = $repo['full_name'];
        if ($event === 'repository' && $action === 'renamed') {
            $previous = $payload['changes']['repository']['name']['from'] ?? null;
            if (! $this->validName($previous)) {
                return response()->json(['error' => 'Invalid previous repository'], 422);
            }
            $identity = $owner.'/'.$previous;
        }
        $allowed = config('services.github.webhook_repositories', []);
        if (! is_array($allowed) || ! in_array(strtolower($identity), array_map('strtolower', $allowed), true)) {
            return response()->json(['error' => 'Repository not authorized'], 403);
        }
        if ($event === 'push' || ($event === 'repository' && in_array($action, [
            'publicized', 'privatized', 'edited', 'archived', 'unarchived', 'renamed', 'deleted',
        ], true))) {
            $cache->invalidate($owner);

            return response()->json(['status' => 'invalidated']);
        }

        return response()->json(['status' => $event === 'ping' ? 'pong' : 'ignored']);
    }

    private function validName(mixed $name): bool
    {
        return is_string($name) && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,99}\z/', $name) === 1;
    }
}
