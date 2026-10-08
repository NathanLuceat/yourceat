<?php

namespace Tests\Feature;

use App\Services\GitHubService;
use App\Support\GitHubReadmeRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GitHubReadmeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'session.driver' => 'array',
            'services.github.username' => 'Owner',
            'services.github.token' => 'test-secret',
            'services.github.cache_ttl' => 300,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_endpoint_fetches_and_caches_sanitized_readme(): void
    {
        $this->fakeRepos();
        Http::fake([
            'https://api.github.com/repos/Owner/demo' => Http::response(['private' => false, 'default_branch' => 'main']),
            'https://api.github.com/repos/Owner/demo/readme' => Http::response($this->payload("# Heading\n\n[Guide](../GUIDE.md)\n\n<script>alert(1)</script>")),
        ]);

        $response = $this->getJson('/projetos/demo/readme')->assertOk()->assertJsonPath('status', 'available');
        $this->assertStringContainsString('<h3>Heading</h3>', $response->json('html'));
        $this->assertStringContainsString('https://github.com/Owner/demo/blob/main/GUIDE.md', $response->json('html'));
        $this->assertStringNotContainsString('<script>', $response->json('html'));
        $this->assertStringNotContainsString('test-secret', $response->getContent());
        $this->getJson('/projetos/demo/readme')->assertOk()->assertExactJson($response->json());
        Http::assertSentCount(3);
    }

    public function test_missing_readme_is_negatively_cached_for_a_short_time(): void
    {
        $this->freezeTime();
        $this->fakeRepos();
        Http::fake([
            'https://api.github.com/repos/Owner/demo' => Http::response(['private' => false, 'default_branch' => 'main']),
            'https://api.github.com/repos/Owner/demo/readme' => Http::sequence()->push([], 404)->push($this->payload('Now present')),
        ]);
        $this->getJson('/projetos/demo/readme')->assertOk()->assertJsonPath('status', 'missing');
        $this->getJson('/projetos/demo/readme')->assertOk()->assertJsonPath('status', 'missing');
        Http::assertSentCount(3);
        $this->travel(61)->seconds();
        $this->getJson('/projetos/demo/readme')->assertOk()->assertJsonPath('status', 'available');
        Http::assertSentCount(5);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payload_is_unavailable_without_exposing_details(array $payload): void
    {
        $this->fakeRepos();
        Http::fake([
            'https://api.github.com/repos/Owner/demo' => Http::response(['private' => false, 'default_branch' => 'main']),
            'https://api.github.com/repos/Owner/demo/readme' => Http::response($payload),
        ]);
        $this->getJson('/projetos/demo/readme')->assertStatus(503)->assertExactJson([
            'status' => 'unavailable', 'html' => null, 'url' => 'https://github.com/Owner/demo#readme',
        ]);
    }

    public static function invalidPayloads(): array
    {
        return [
            'base64' => [['size' => 2, 'encoding' => 'base64', 'content' => '***test-secret***', 'path' => 'README.md']],
            'encoding' => [['size' => 2, 'encoding' => 'utf8', 'content' => 'hi', 'path' => 'README.md']],
            'length mismatch' => [['size' => 20, 'encoding' => 'base64', 'content' => base64_encode('hi'), 'path' => 'README.md']],
            'missing content' => [['size' => 2, 'encoding' => 'base64', 'path' => 'README.md']],
            'negative size' => [['size' => -1]],
            'size type' => [['size' => '2']],
        ];
    }

    public function test_large_readme_offers_original_without_silent_truncation(): void
    {
        $this->fakeRepos();
        Http::fake([
            'https://api.github.com/repos/Owner/demo' => Http::response(['private' => false, 'default_branch' => 'main']),
            'https://api.github.com/repos/Owner/demo/readme' => Http::response(['size' => 524289]),
        ]);
        $this->getJson('/projetos/demo/readme')->assertOk()->assertExactJson([
            'status' => 'too_large', 'html' => null, 'url' => 'https://github.com/Owner/demo#readme',
        ]);
    }

    #[DataProvider('failureStatuses')]
    public function test_transient_failures_serve_last_good_readme_without_overwriting_it(int $status): void
    {
        $this->freezeTime();
        $this->fakeRepos();
        Http::fake([
            'https://api.github.com/repos/Owner/demo' => Http::response(['private' => false, 'default_branch' => 'main']),
            'https://api.github.com/repos/Owner/demo/readme' => Http::sequence()->push($this->payload('Good README'))->push(['message' => 'test-secret'], $status)->push(['message' => 'test-secret'], $status),
        ]);
        $good = $this->getJson('/projetos/demo/readme')->assertOk()->json();
        $this->travel(301)->seconds();
        $this->getJson('/projetos/demo/readme')->assertOk()->assertExactJson($good);
        $this->travel(24)->hours();
        $this->getJson('/projetos/demo/readme')->assertStatus(503)->assertJsonPath('status', 'unavailable');
    }

    public static function failureStatuses(): array
    {
        return [[500], [429], [403]];
    }

    public function test_network_failure_returns_safe_retryable_response(): void
    {
        $this->fakeRepos();
        Http::fake(['https://api.github.com/repos/Owner/demo' => Http::failedConnection('test-secret')]);
        $response = $this->getJson('/projetos/demo/readme')->assertStatus(503)->assertJsonPath('status', 'unavailable');
        $this->assertStringNotContainsString('test-secret', $response->getContent());
    }

    public function test_exact_size_limit_is_rendered(): void
    {
        $this->fakeRepos();
        Http::fake([
            'https://api.github.com/repos/Owner/demo' => Http::response(['private' => false, 'default_branch' => 'main']),
            'https://api.github.com/repos/Owner/demo/readme' => Http::response($this->payload(str_repeat('a', 524288))),
        ]);
        $this->getJson('/projetos/demo/readme')->assertOk()->assertJsonPath('status', 'available');
    }

    public function test_listing_projects_does_not_prefetch_readmes(): void
    {
        $this->fakeRepos();
        $this->assertCount(1, app(GitHubService::class)->repos());
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/readme'));
    }

    public function test_cold_failure_can_be_retried(): void
    {
        $this->fakeRepos();
        Http::fake([
            'https://api.github.com/repos/Owner/demo' => Http::response(['private' => false, 'default_branch' => 'main']),
            'https://api.github.com/repos/Owner/demo/readme' => Http::sequence()->push([], 500)->push($this->payload('Retry works')),
        ]);
        $this->getJson('/projetos/demo/readme')->assertStatus(503);
        $this->getJson('/projetos/demo/readme')->assertOk()->assertJsonPath('status', 'available');
    }

    public function test_only_public_eligible_repositories_are_allowed(): void
    {
        $this->fakeRepos([
            $this->repo('private', ['private' => true]),
            $this->repo('unknown-visibility', ['private' => null]),
            $this->repo('fork', ['fork' => true]),
            $this->repo('archived', ['archived' => true]),
            $this->repo('Owner'),
        ]);
        foreach (['private', 'unknown-visibility', 'fork', 'archived', 'Owner', 'not-listed'] as $repo) {
            $this->getJson('/projetos/'.$repo.'/readme')->assertNotFound();
        }
        $this->assertNull(app(GitHubService::class)->projectReadme('../other'));
        Http::assertSentCount(1);
    }

    public function test_visibility_is_rechecked_on_cold_readme_request(): void
    {
        $this->fakeRepos();
        Http::fake(['https://api.github.com/repos/Owner/demo' => Http::response(['private' => true, 'default_branch' => 'main'])]);
        $this->getJson('/projetos/demo/readme')->assertStatus(503);
        Http::assertSentCount(2);
    }

    public function test_cache_does_not_cross_owners_or_repositories(): void
    {
        $this->fakeRepos([$this->repo('demo'), $this->repo('second')]);
        Http::fake([
            'https://api.github.com/repos/Owner/demo' => Http::response(['private' => false, 'default_branch' => 'main']),
            'https://api.github.com/repos/Owner/demo/readme' => Http::response($this->payload('First')),
            'https://api.github.com/repos/Owner/second' => Http::response(['private' => false, 'default_branch' => 'main']),
            'https://api.github.com/repos/Owner/second/readme' => Http::response($this->payload('Second')),
            'https://api.github.com/users/Other/repos*' => Http::response([$this->repo('demo')]),
            'https://api.github.com/repos/Other/demo' => Http::response(['private' => false, 'default_branch' => 'main']),
            'https://api.github.com/repos/Other/demo/readme' => Http::response($this->payload('Other owner')),
        ]);
        $this->assertStringContainsString('First', app(GitHubService::class)->projectReadme('demo')['html']);
        $this->assertStringContainsString('Second', app(GitHubService::class)->projectReadme('second')['html']);
        config(['services.github.username' => 'Other']);
        $this->assertStringContainsString('Other owner', app(GitHubService::class)->projectReadme('demo')['html']);
        Http::assertSentCount(8);
    }

    public function test_lock_prevents_duplicate_cold_fetches_and_is_not_released_by_nonowner(): void
    {
        $this->fakeRepos();
        $key = 'gh:readme-v1:'.hash('sha256', 'Owner/demo').':lock';
        $lock = Cache::lock($key, 30);
        $this->assertTrue($lock->get());
        $this->getJson('/projetos/demo/readme')->assertStatus(503);
        $this->assertFalse(Cache::lock($key, 30)->get());
        $lock->release();
        Http::assertSentCount(1);
    }

    public function test_renderer_resolves_links_images_anchors_and_escapes_code(): void
    {
        $markdown = <<<'MD'
# Title
[up](../GUIDE.md?view=1#setup)
[root](/LICENSE)
[index](#install)
[query](?plain=1)
![image](./images/a%20b.png)
[web](https://example.test/page)
[bad](javascript:alert%281%29)
![data](data:text/html;base64,AAAA)
<iframe src="https://evil.test"></iframe>

```html
<script>alert('code')</script>
```

| Column | Value |
| --- | --- |
| One | Two |
MD;
        $html = app(GitHubReadmeRenderer::class)->render($markdown, 'Owner', 'demo', 'feature/docs', 'docs/README.md');
        foreach ([
            '<h3>Title</h3>',
            'https://github.com/Owner/demo/blob/feature%2Fdocs/GUIDE.md?view=1#setup',
            'https://github.com/Owner/demo/blob/feature%2Fdocs/LICENSE',
            'https://github.com/Owner/demo/blob/feature%2Fdocs/docs/README.md#install',
            'https://github.com/Owner/demo/blob/feature%2Fdocs/docs/README.md?plain=1',
            'https://raw.githubusercontent.com/Owner/demo/feature%2Fdocs/docs/images/a%20b.png',
            'https://example.test/page', '&lt;script&gt;', '<table>',
        ] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        foreach (['javascript:', 'data:text', '<iframe', '<script>', 'href="#install"'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $html);
        }
        Http::assertNothingSent();
    }

    private function payload(string $markdown): array
    {
        return ['size' => strlen($markdown), 'encoding' => 'base64', 'content' => base64_encode($markdown), 'path' => 'docs/README.md'];
    }

    private function repo(string $name, array $extra = []): array
    {
        return array_replace(['name' => $name, 'private' => false, 'fork' => false, 'archived' => false, 'default_branch' => 'main'], $extra);
    }

    private function fakeRepos(?array $repos = null): void
    {
        Http::fake(['https://api.github.com/users/Owner/repos*' => Http::response($repos ?? [$this->repo('demo')])]);
    }
}
