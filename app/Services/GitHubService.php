<?php

namespace App\Services;

use App\Support\GitHubCache;
use App\Support\GitHubReadmeRenderer;
use Closure;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use UnexpectedValueException;

class GitHubService
{
    private string $user;

    private int $ttl;

    public function __construct()
    {
        $this->user = config('services.github.username');
        $this->ttl = config('services.github.cache_ttl');
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
        return $this->cached('public-repos-v2', function () {
            $repos = $this->request("/users/{$this->user}/repos", [
                'type' => 'owner', 'sort' => 'pushed', 'per_page' => 100,
            ])->throw()->json();

            return collect($repos)
                ->reject(fn ($r) => ($r['private'] ?? true) !== false || $r['fork'] || $r['archived'] || $r['name'] === $this->user)
                ->map(fn ($r) => Arr::only($r, [
                    'name', 'description', 'language', 'stargazers_count',
                    'html_url', 'homepage', 'topics', 'pushed_at', 'default_branch', 'private',
                ]))
                ->values()->all();
        }) ?? [];
    }

    public function recentCommits(int $repos = 4, int $limit = 10): array
    {
        return $this->cached('commits', function () use ($repos, $limit) {
            return collect($this->repos())->take($repos)
                ->flatMap(function ($repo) {
                    if (! $this->isPublic($repo['name'])) {
                        return [];
                    }
                    $res = $this->request("/repos/{$this->user}/{$repo['name']}/commits", [
                        'author' => $this->user, 'per_page' => 5,
                    ]);

                    // repositório vazio (409) ou erro isolado não derruba a lista toda
                    return $res->successful()
                        ? collect($res->json())->map(fn ($c) => [
                            'repo' => $repo['name'],
                            'sha' => substr($c['sha'], 0, 7),
                            'message' => Str::before($c['commit']['message'], "\n"),
                            'date' => $c['commit']['author']['date'],
                            'url' => $c['html_url'],
                        ])
                        : [];
                })
                ->sortByDesc('date')->take($limit)->values()->all();
        }) ?? [];
    }

    public function profileReadmeHtml(): ?string
    {
        return $this->cached('readme', function () {
            if (! $this->isPublic($this->user)) {
                return null;
            }
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

    public function projectReadme(string $repo): ?array
    {
        return $this->inGeneration(fn (string $generation) => $this->projectReadmeInGeneration($repo, $generation));
    }

    private function projectReadmeInGeneration(string $repo, string $generation): ?array
    {
        if (! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,99}\z/', $repo)) {
            return null;
        }
        $project = collect($this->repos())->first(fn ($item) => ($item['name'] ?? null) === $repo && ($item['private'] ?? true) === false);
        if ($project === null) {
            return null;
        }

        $url = 'https://github.com/'.rawurlencode($this->user).'/'.rawurlencode($repo).'#readme';
        $unavailable = ['status' => 'unavailable', 'html' => null, 'url' => $url];
        $key = app(GitHubCache::class)->key($this->user, $generation, 'project-readme:'.hash('sha256', $repo));
        $lock = null;
        $public = false;
        try {
            if ($fresh = Cache::get($key)) {
                return $fresh;
            }
            $lock = Cache::lock($key.':lock', 30);
            if (! $lock->get()) {
                return Cache::get($key.':stale', $unavailable);
            }
            if ($fresh = Cache::get($key)) {
                return $fresh;
            }

            // Recheck visibility before fetching with a token that might also read private repos.
            $metadata = $this->request("/repos/{$this->user}/{$repo}")->throw()->json();
            if (($metadata['private'] ?? true) !== false || ! is_string($metadata['default_branch'] ?? null)) {
                Cache::forget($key.':stale');

                return $unavailable;
            }
            $public = true;
            $response = $this->request("/repos/{$this->user}/{$repo}/readme");
            if ($response->status() === 404) {
                $missing = ['status' => 'missing', 'html' => null, 'url' => $url];
                Cache::put($key, $missing, 60);
                Cache::forget($key.':stale');

                return $missing;
            }
            $payload = $response->throw()->json();
            if (! is_array($payload) || ! is_int($payload['size'] ?? null) || $payload['size'] < 0) {
                throw new UnexpectedValueException('Invalid README metadata');
            }
            if ($payload['size'] > 524288) {
                $result = ['status' => 'too_large', 'html' => null, 'url' => $url];
            } else {
                if (($payload['encoding'] ?? null) !== 'base64' || ! is_string($payload['content'] ?? null)
                    || strlen($payload['content']) > 750000 || ! is_string($payload['path'] ?? null)) {
                    throw new UnexpectedValueException('Invalid README content');
                }
                $markdown = base64_decode($payload['content'], true);
                if ($markdown === false || strlen($markdown) !== $payload['size']) {
                    throw new UnexpectedValueException('Invalid README encoding');
                }
                $result = [
                    'status' => 'available',
                    'html' => app(GitHubReadmeRenderer::class)->render($markdown, $this->user, $repo, $metadata['default_branch'], $payload['path']),
                    'url' => $url,
                ];
            }
            Cache::put($key, $result, $this->ttl);
            Cache::put($key.':stale', $result, now()->addDay());

            return $result;
        } catch (Throwable) {
            Log::warning('GitHub README unavailable', ['repo' => $repo]);

            return $public ? Cache::get($key.':stale', $unavailable) : $unavailable;
        } finally {
            $lock?->release();
        }
    }

    private function request(string $path, array $query = [], string $accept = 'application/vnd.github+json'): Response
    {
        $res = Http::baseUrl('https://api.github.com')
            ->withToken(config('services.github.token'))
            ->withHeaders(['Accept' => $accept, 'X-GitHub-Api-Version' => '2022-11-28'])
            ->connectTimeout(5)
            ->timeout(10)
            ->withoutRedirecting()
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
        return $this->inGeneration(function (string $generation) use ($key, $fetch) {
            $fresh = app(GitHubCache::class)->key($this->user, $generation, $key);
            if (Cache::has($fresh)) {
                return Cache::get($fresh);
            }
            try {
                $data = $fetch();
                Cache::put($fresh, $data, $this->ttl);
                Cache::put("{$fresh}:stale", $data, now()->addDay());

                return $data;
            } catch (Throwable) {
                Log::warning('GitHub API falhou', ['chave' => $key]);

                return Cache::get("{$fresh}:stale");
            }
        });
    }

    private function inGeneration(Closure $fetch): mixed
    {
        $cache = app(GitHubCache::class);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $generation = $cache->generation($this->user);
            $result = $fetch($generation);
            if ($cache->current($this->user, $generation)) {
                return $result;
            }
        }

        return null;
    }

    private function isPublic(string $repo): bool
    {
        try {
            $response = $this->request("/repos/{$this->user}/{$repo}");

            return $response->successful() && $response->json('private') === false;
        } catch (Throwable) {
            return false;
        }
    }
}
