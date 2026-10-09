<?php

namespace Tests\Feature;

use App\Support\GitHubCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GitHubWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array',
            'services.github.username' => 'Owner', 'services.github.webhook_secret' => 'fixture-secret',
            'services.github.webhook_repositories' => ['Owner/demo']]);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake();
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent();
        parent::tearDown();
    }

    public function test_secret_signature_and_raw_body_fail_closed(): void
    {
        $this->post('/webhooks/github')->assertStatus(401)->assertJsonPath('error', 'Invalid signature');
        $this->deliver($this->payload(), signature: 'sha256='.str_repeat('0', 64))->assertStatus(401);
        $this->deliver($this->payload(), signature: 'sha256='.hash_hmac('sha256', '{}', 'fixture-secret'))->assertStatus(401);
        config(['services.github.webhook_secret' => '']);
        $this->deliver($this->payload())->assertStatus(503);
    }

    public function test_invalid_json_types_and_size_are_json_errors_without_accept_header(): void
    {
        $this->deliver('{')->assertStatus(400);
        $this->deliver('[]')->assertStatus(422);
        $this->deliver(str_repeat('x', 1048577))->assertStatus(413);
        $this->deliver(str_repeat('x', 1048576))->assertStatus(400);
        $this->deliver(json_encode(['repository' => ['name' => []]]))->assertStatus(403);
    }

    public function test_owner_allowlist_and_full_name_must_agree(): void
    {
        foreach ([
            ['name' => 'demo', 'full_name' => 'Other/demo', 'owner' => ['login' => 'Other']],
            ['name' => 'other', 'full_name' => 'Owner/other', 'owner' => ['login' => 'Owner']],
            ['name' => 'demo', 'full_name' => 'Other/demo', 'owner' => ['login' => 'Owner']],
        ] as $repo) {
            $this->deliver(json_encode(['repository' => $repo]))->assertStatus(403);
        }
        config(['services.github.webhook_repositories' => []]);
        $this->deliver($this->payload())->assertStatus(403);
    }

    public function test_ping_and_unused_events_do_not_invalidate(): void
    {
        $cache = app(GitHubCache::class);
        $before = $cache->generation('Owner');
        $this->deliver($this->payload(), 'ping')->assertOk()->assertJsonPath('status', 'pong');
        $this->deliver($this->payload(), 'issues')->assertOk()->assertJsonPath('status', 'ignored');
        $this->deliver($this->payload(['action' => 'created']), 'repository')->assertOk()->assertJsonPath('status', 'ignored');
        $this->assertSame($before, $cache->generation('Owner'));
    }

    public function test_push_repeated_deliveries_and_repository_events_rotate_only_owner(): void
    {
        $cache = app(GitHubCache::class);
        $other = $cache->generation('Other');
        Cache::put('unrelated', 'keep');
        foreach (['push', 'push', 'publicized', 'privatized', 'edited', 'archived', 'unarchived', 'deleted'] as $event) {
            $before = $cache->generation('Owner');
            $this->deliver($this->payload(['action' => $event]), $event === 'push' ? 'push' : 'repository')
                ->assertOk()->assertJsonPath('status', 'invalidated');
            $this->assertNotSame($before, $cache->generation('Owner'));
        }
        $this->assertSame($other, $cache->generation('Other'));
        $this->assertSame('keep', Cache::get('unrelated'));
    }

    public function test_rename_requires_previous_authorized_identity_and_new_name_remains_disallowed(): void
    {
        $body = $this->payload(['repository' => ['name' => 'new', 'full_name' => 'Owner/new', 'owner' => ['login' => 'Owner']],
            'action' => 'renamed', 'changes' => ['repository' => ['name' => ['from' => 'demo']]]]);
        $this->deliver($body, 'repository')->assertOk()->assertJsonPath('status', 'invalidated');
        $this->deliver($body, 'push')->assertStatus(403);
        $this->deliver($this->payload(['action' => 'renamed']), 'repository')->assertStatus(422);
        $this->deliver(str_replace('"from":"demo"', '"from":"other"', $body), 'repository')->assertStatus(403);
    }

    public function test_dedicated_rate_limit_returns_json(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->deliver($this->payload(), 'ping')->assertOk();
        }
        $this->deliver($this->payload(), 'ping')->assertStatus(429)->assertJsonPath('error', 'Too many requests');
    }

    public function test_only_webhook_post_is_exempt_from_csrf(): void
    {
        // Laravel skips CSRF in unit tests; switch only the environment binding for this request.
        $this->app->instance('env', 'local');
        Route::middleware('web')->post('/csrf-probe', fn () => response()->json(['ok' => true]));
        Route::middleware('web')->put('/webhooks/github', fn () => response()->json(['ok' => true]));
        $this->deliver($this->payload(), 'ping')->assertOk();
        $this->post('/csrf-probe')->assertStatus(419);
        $this->put('/webhooks/github')->assertStatus(419)->assertHeader('Content-Type', 'application/json');
    }

    private function payload(array $extra = []): string
    {
        return json_encode(array_replace(['repository' => ['name' => 'demo', 'full_name' => 'Owner/demo', 'owner' => ['login' => 'Owner']]], $extra));
    }

    private function deliver(string $body, string $event = 'push', ?string $signature = null)
    {
        return $this->call('POST', '/webhooks/github', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_GITHUB_EVENT' => $event,
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256='.hash_hmac('sha256', $body, 'fixture-secret'),
        ], $body);
    }
}
