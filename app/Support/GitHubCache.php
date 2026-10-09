<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class GitHubCache
{
    public function generation(string $owner): string
    {
        $key = $this->generationKey($owner);
        $token = bin2hex(random_bytes(24));
        Cache::add($key, $token, now()->addDays(2));

        // If evicted concurrently, use an unreachable namespace, never an old default.
        return Cache::get($key) ?? $token;
    }

    public function invalidate(string $owner): void
    {
        Cache::put($this->generationKey($owner), bin2hex(random_bytes(24)), now()->addDays(2));
    }

    public function current(string $owner, string $generation): bool
    {
        return Cache::get($this->generationKey($owner)) === $generation;
    }

    public function key(string $owner, string $generation, string $family): string
    {
        return 'gh:v3:'.hash('sha256', strtolower($owner)).':'.$generation.':'.$family;
    }

    public function generationKey(string $owner): string
    {
        return 'gh:generation:'.hash('sha256', strtolower($owner));
    }
}
