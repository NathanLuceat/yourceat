<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GitHubService
{
    private string $user;
    private int $ttl;

    public function __construct()
    {
        $this->user = config('services.github.username');
        $this->ttl  = config('services.github.cache_ttl');
    }

    public function profile(): ?array
    {
        return $this->cached('profile', fn () => Arr::only(
            $this->request("/users/{$this->user}")->throw()->json(),
            ['login', 'name', 'avatar_url', 'bio', 'location', 'blog',
             'followers', 'following', 'public_repos', 'html_url']
        ));
    }

    public function repos(): array
    {
        return $this->cached('repos', function () {
            $repos = $this->request("/users/{$this->user}/repos", [
                'type' => 'owner', 'sort' => 'pushed', 'per_page' => 100,
            ])->throw()->json();

            return collect($repos)
                ->reject(fn ($r) => $r['fork'] || $r['archived'] || $r['name'] === $this->user)
                ->map(fn ($r) => Arr::only($r, [
                    'name', 'description', 'language', 'stargazers_count',
                    'html_url', 'homepage', 'topics', 'pushed_at',
                ]))
                ->values()->all();
        }) ?? [];
    }

    public function recentCommits(int $repos = 4, int $limit = 10): array
    {
        return $this->cached('commits', function () use ($repos, $limit) {
            return collect($this->repos())->take($repos)
                ->flatMap(function ($repo) {
                    $res = $this->request("/repos/{$this->user}/{$repo['name']}/commits", [
                        'author' => $this->user, 'per_page' => 5,
                    ]);

                    // repositório vazio (409) ou erro isolado não derruba a lista toda
                    return $res->successful()
                        ? collect($res->json())->map(fn ($c) => [
                            'repo'    => $repo['name'],
                            'sha'     => substr($c['sha'], 0, 7),
                            'message' => Str::before($c['commit']['message'], "\n"),
                            'date'    => $c['commit']['author']['date'],
                            'url'     => $c['html_url'],
                        ])
                        : [];
                })
                ->sortByDesc('date')->take($limit)->values()->all();
        }) ?? [];
    }

    public function profileReadmeHtml(): ?string
    {
        return $this->cached('readme', function () {
            $markdown = $this->request(
                "/repos/{$this->user}/{$this->user}/readme",
                accept: 'application/vnd.github.raw+json'
            )->throw()->body();

            // remove HTML cru do README para evitar XSS
            return Str::markdown($markdown, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        });
    }

    private function request(string $path, array $query = [], string $accept = 'application/vnd.github+json'): Response
    {
        $res = Http::baseUrl('https://api.github.com')
            ->withToken(config('services.github.token'))
            ->withHeaders(['Accept' => $accept, 'X-GitHub-Api-Version' => '2022-11-28'])
            ->timeout(10)
            ->get($path, $query);

        $left = $res->header('X-RateLimit-Remaining');
        if ($left !== '' && (int) $left < 100) {
            Log::warning('GitHub: poucas requisições restantes', ['restantes' => $left]);
        }

        return $res;
    }

    /**
     * Cache em dois níveis: uma cópia "fresca" (TTL curto) e uma cópia
     * "velha" (24h). Se o GitHub falhar ou o limite estourar, serve a velha.
     */
    private function cached(string $key, Closure $fetch): mixed
    {
        $fresh = "gh:{$key}";

        if (Cache::has($fresh)) {
            return Cache::get($fresh);
        }

        try {
            $data = $fetch();
            Cache::put($fresh, $data, $this->ttl);
            Cache::put("{$fresh}:stale", $data, now()->addDay());
            return $data;
        } catch (\Throwable $e) {
            Log::warning('GitHub API falhou', ['chave' => $key, 'erro' => $e->getMessage()]);
            return Cache::get("{$fresh}:stale");
        }
    }
}