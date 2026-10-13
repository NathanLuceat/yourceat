<?php

namespace Tests\Feature;

use App\Services\GitHubService;
use App\Support\GitHubCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GitHubCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'services.github.username' => 'Owner',
            'services.github.token' => 'fixture', 'services.github.cache_ttl' => 300]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_all_cache_families_and_locks_are_namespaced_and_token_loss_cannot_resurrect_them(): void
    {
        $cache = app(GitHubCache::class);
        $old = $cache->generation('Owner');
        foreach (['profile', 'public-repos-v2', 'commits', 'readme', 'project-readme:'.hash('sha256', 'demo')] as $family) {
            foreach (['', ':stale', ':lock'] as $suffix) {
                Cache::put($cache->key('Owner', $old, $family).$suffix, 'old');
            }
        }
        Cache::forget($cache->generationKey('Owner'));
        $new = $cache->generation('Owner');
        $this->assertNotSame($old, $new);
        $this->assertSame($new, $cache->generation('owner'));
        Http::fake(['*' => Http::response([], 500)]);
        $service = app(GitHubService::class);
        $this->assertNull($service->profile());
        $this->assertSame([], $service->repos());
        $this->assertSame([], $service->recentCommits());
        $this->assertNull($service->profileReadmeHtml());
        $this->assertNull($service->projectReadme('demo'));
    }

    public function test_mid_http_invalidation_discards_old_result_and_retries_once(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                app(GitHubCache::class)->invalidate('Owner');
            }

            return Http::response(['name' => $calls === 1 ? 'old' : 'new']);
        });
        $this->assertSame('new', app(GitHubService::class)->profile()['name']);
        Http::assertSentCount(2);
    }

    public function test_repeated_invalidation_has_bounded_retry_and_never_returns_old_stale(): void
    {
        $cache = app(GitHubCache::class);
        Cache::put($cache->key('Owner', $cache->generation('Owner'), 'profile').':stale', ['name' => 'old']);
        Http::fake(function () use ($cache) {
            $cache->invalidate('Owner');

            return Http::response([], 500);
        });
        $this->assertNull(app(GitHubService::class)->profile());
        Http::assertSentCount(2);
    }

    public function test_same_generation_public_fallback_remains_available(): void
    {
        $this->freezeTime();
        Http::fake(['*' => Http::sequence()->push(['name' => 'public'])->push([], 500)]);
        $service = app(GitHubService::class);
        $this->assertSame('public', $service->profile()['name']);
        $this->travel(301)->seconds();
        $this->assertSame('public', $service->profile()['name']);
    }

    public function test_project_negative_cache_is_invalidated(): void
    {
        $this->fakeProject();
        Http::fake(['https://api.github.com/repos/Owner/demo/readme' => Http::sequence()->push([], 404)->push($this->readme('new'))]);
        $service = app(GitHubService::class);
        $this->assertSame('missing', $service->projectReadme('demo')['status']);
        app(GitHubCache::class)->invalidate('Owner');
        $this->assertStringContainsString('new', $service->projectReadme('demo')['html']);
    }

    public function test_project_race_discards_rendered_content_and_previous_stale_when_repository_becomes_private(): void
    {
        $this->fakeProject(['https://api.github.com/repos/Owner/demo' => Http::sequence()
            ->push(['private' => false, 'default_branch' => 'main'])->push(['private' => true]),
            'https://api.github.com/repos/Owner/demo/readme' => function () {
                app(GitHubCache::class)->invalidate('Owner');

                return Http::response($this->readme('must not escape'));
            }]);
        $result = app(GitHubService::class)->projectReadme('demo');
        $this->assertSame('unavailable', $result['status']);
        $this->assertNull($result['html']);
    }

    public function test_profile_readme_and_commits_do_not_fetch_private_content_with_token(): void
    {
        $this->fakeProject([
            'https://api.github.com/repos/Owner/demo' => Http::response(['private' => true]),
            'https://api.github.com/repos/Owner/Owner' => Http::response(['private' => true]),
        ]);
        $service = app(GitHubService::class);
        $this->assertNull($service->profileReadmeHtml());
        $this->assertSame([], $service->recentCommits());
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/readme') || str_contains($request->url(), '/commits'));
    }

    public function test_recent_commits_returns_the_nine_newest_entries(): void
    {
        Http::fake([
            'https://api.github.com/users/Owner/repos*' => Http::response(collect(['first', 'second'])->map(fn ($name) => [
                'name' => $name, 'private' => false, 'fork' => false, 'archived' => false,
            ])->all()),
            'https://api.github.com/repos/Owner/*/commits*' => Http::response(collect(range(1, 5))->map(fn ($day) => [
                'sha' => str_repeat((string) $day, 40),
                'commit' => ['message' => 'Commit '.$day, 'author' => ['date' => '2026-10-0'.$day.'T12:00:00Z']],
                'html_url' => 'https://github.com/Owner/demo/commit/'.$day,
            ])->all()),
            'https://api.github.com/repos/Owner/*' => Http::response(['private' => false]),
        ]);

        $commits = app(GitHubService::class)->recentCommits();

        $this->assertCount(9, $commits);
        $this->assertSame('Commit 5', $commits[0]['message']);
        $this->assertSame('Commit 1', $commits[8]['message']);
        $this->assertSame(['first', 'second'], array_values(array_unique(array_column($commits, 'repo'))));
    }

    public function test_recent_commits_limits_existing_fresh_and_stale_cache_to_nine(): void
    {
        $cache = app(GitHubCache::class);
        $key = $cache->key('Owner', $cache->generation('Owner'), 'commits');
        $old = array_map(fn ($number) => ['message' => 'Commit '.$number], range(1, 10));
        Cache::put($key, $old);
        Cache::put($key.':stale', $old);
        $service = app(GitHubService::class);

        $this->assertSame(array_slice($old, 0, 9), $service->recentCommits());
        Http::assertNothingSent();

        Cache::forget($key);
        $failingService = new class extends GitHubService
        {
            public function repos(): array
            {
                throw new RuntimeException('Fixture failure');
            }
        };
        $this->assertSame(array_slice($old, 0, 9), $failingService->recentCommits());
    }

    private function fakeProject(array $overrides = []): void
    {
        Http::fake(array_replace([
            'https://api.github.com/users/Owner/repos*' => Http::response([['name' => 'demo', 'private' => false, 'fork' => false, 'archived' => false]]),
            'https://api.github.com/repos/Owner/demo' => Http::response(['private' => false, 'default_branch' => 'main']),
        ], $overrides));
    }

    private function readme(string $text): array
    {
        return ['size' => strlen($text), 'encoding' => 'base64', 'content' => base64_encode($text), 'path' => 'README.md'];
    }
}
