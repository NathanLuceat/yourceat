<?php

namespace Tests\Feature;

use App\Models\WebbliotecaActivity;
use App\Services\WebbliotecaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WebbliotecaSyncTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://webblioteca.example.test/api/v1/activities*';

    protected function beforeRefreshingDatabase(): void
    {
        // This hook runs before RefreshDatabase can migrate or open a connection.
        config([
            'database.default' => 'sqlite',
            'database.connections' => ['sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]],
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        Http::preventStrayRequests();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Cache::flush();
        config([
            'services.webblioteca.url' => 'https://webblioteca.example.test/',
            'services.webblioteca.token' => '  bEaReR test-secret-token  ',
            'app.timezone' => 'UTC',
        ]);
    }

    public function test_sync_normalizes_bearer_and_imports_only_public_activity_fields(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['data' => [
            self::activity('9007199254740993'),
            self::activity('9007199254740994', ['actor' => ['id' => null], 'summary' => null]),
        ]])]);

        app(WebbliotecaService::class)->sync();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://webblioteca.example.test/api/v1/activities?')
            && $request->hasHeader('Authorization', 'Bearer test-secret-token')
            && $request->hasHeader('Accept', 'application/json')
            && $request['since_id'] === '0'
            && (int) $request['limit'] === 50);
        $this->assertDatabaseCount('webblioteca_activities', 2);
        $activity = WebbliotecaActivity::where('external_id', '9007199254740993')->sole();
        $this->assertSame('9007199254740993', (string) $activity->external_id);
        $this->assertSame('emprestimo.criado', $activity->type);
        $this->assertSame(['livro' => 'Livro de teste'], $activity->summary);
        $this->assertFalse($activity->is_system);
        $this->assertSame('2026-10-07T12:00:00+00:00', $activity->occurred_at->toIso8601String());
        $system = WebbliotecaActivity::where('external_id', '9007199254740994')->sole();
        $this->assertTrue($system->is_system);
        $this->assertSame([], $system->summary);
        $stored = json_encode(DB::table('webblioteca_activities')->get(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Private Actor', $stored);
        $this->assertStringNotContainsString('private@example.test', $stored);
        $this->assertStringNotContainsString('actor', $stored);
    }

    public function test_empty_batch_does_not_write_or_change_the_cursor(): void
    {
        $existing = $this->persistActivity(7)->fresh()->getAttributes();
        Http::fake([self::ENDPOINT => Http::response(['data' => []])]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        app(WebbliotecaService::class)->sync();

        $this->assertNoWrites();
        $this->assertSame($existing, WebbliotecaActivity::sole()->getAttributes());
        Http::assertSent(fn (Request $request) => $request['since_id'] === '7');
        Http::assertSentCount(1);
    }

    #[DataProvider('httpFailures')]
    public function test_http_failure_does_not_write_or_log_remote_secrets(int $status): void
    {
        Log::spy();
        Http::fake([self::ENDPOINT => Http::response(['error' => 'Private Actor private@example.test test-secret-token'], $status)]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        app(WebbliotecaService::class)->sync();

        $this->assertNoWrites();
        $this->assertDatabaseCount('webblioteca_activities', 0);
        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->with('Webblioteca sync failed', ['stage' => 'request', 'http_status' => $status]);
    }

    public static function httpFailures(): array
    {
        return ['unauthorized' => [401], 'forbidden' => [403], 'rate limited' => [429]];
    }

    public function test_network_failure_does_not_write_or_escape_the_service(): void
    {
        Log::spy();
        Http::fake([self::ENDPOINT => Http::failedConnection('Private Actor test-secret-token')]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        app(WebbliotecaService::class)->sync();

        $this->assertNoWrites();
        $this->assertDatabaseCount('webblioteca_activities', 0);
        Log::shouldHaveReceived('warning')->once()->with('Webblioteca sync failed', ['stage' => 'request', 'http_status' => null]);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_batch_is_rejected_before_any_write(string $body): void
    {
        Log::spy();
        Http::fake([self::ENDPOINT => Http::response($body, 200, ['Content-Type' => 'application/json'])]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        app(WebbliotecaService::class)->sync();

        $this->assertNoWrites();
        $this->assertDatabaseCount('webblioteca_activities', 0);
        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->with('Webblioteca sync failed', ['stage' => 'payload', 'http_status' => 200]);
    }

    public static function invalidPayloads(): iterable
    {
        yield 'malformed JSON' => ['{"data":'];
        foreach ([
            'null envelope' => null,
            'scalar envelope' => 'not an envelope',
            'missing data' => ['items' => []],
            'null data' => ['data' => null],
            'scalar data' => ['data' => 'invalid'],
            'keyed data' => ['data' => ['activity' => self::activity(1)]],
            'too many rows' => ['data' => array_map(fn ($id) => self::activity($id), range(1, 51))],
            'descending IDs' => ['data' => [self::activity(2), self::activity(1)]],
            'duplicate IDs' => ['data' => [self::activity(1), self::activity(1)]],
            'scalar row after valid row' => ['data' => [self::activity(1), 'invalid']],
        ] as $name => $payload) {
            yield $name => [json_encode($payload, JSON_THROW_ON_ERROR)];
        }

        foreach (['id', 'type', 'summary', 'actor', 'created_at'] as $field) {
            $invalid = self::activity(2);
            unset($invalid[$field]);
            yield 'missing '.$field => [json_encode(['data' => [self::activity(1), $invalid]], JSON_THROW_ON_ERROR)];
        }

        foreach ([
            'zero ID' => ['id' => 0],
            'negative ID' => ['id' => -2],
            'fractional ID' => ['id' => 2.5],
            'float ID' => ['id' => 2.0],
            'boolean ID' => ['id' => true],
            'array ID' => ['id' => []],
            'overflow ID' => ['id' => '9223372036854775808'],
            'non numeric ID' => ['id' => 'two'],
            'blank type' => ['type' => ''],
            'array type' => ['type' => []],
            'overlong type' => ['type' => str_repeat('a', 256)],
            'scalar summary' => ['summary' => 'invalid'],
            'null actor' => ['actor' => null],
            'scalar actor' => ['actor' => 'invalid'],
            'missing actor ID' => ['actor' => ['name' => 'Private Actor']],
            'invalid actor ID' => ['actor' => ['id' => 'invalid']],
            'zero actor ID' => ['actor' => ['id' => 0]],
            'invalid date' => ['created_at' => 'not a date'],
            'null date' => ['created_at' => null],
            'array date' => ['created_at' => []],
        ] as $name => $overrides) {
            yield $name => [json_encode(['data' => [self::activity(1), self::activity(2, $overrides)]], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)];
        }
    }

    public function test_replayed_or_stale_batch_cannot_update_existing_rows_or_advance_cursor(): void
    {
        $existing = $this->persistActivity(7)->fresh()->getAttributes();
        Http::fake([self::ENDPOINT => Http::response(['data' => [
            self::activity(7, ['summary' => ['livro' => 'Changed']]), self::activity(8),
        ]])]);
        DB::enableQueryLog();
        DB::flushQueryLog();

        app(WebbliotecaService::class)->sync();

        $this->assertNoWrites();
        $this->assertDatabaseCount('webblioteca_activities', 1);
        $this->assertSame($existing, WebbliotecaActivity::sole()->getAttributes());
        Http::assertSent(fn (Request $request) => $request['since_id'] === '7');
    }

    public function test_storage_failure_rolls_back_the_entire_batch_and_listener_is_removed(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['data' => [self::activity(1), self::activity(2)]])]);
        $original = WebbliotecaActivity::getEventDispatcher();
        $scoped = clone $original;
        $event = 'eloquent.creating: '.WebbliotecaActivity::class;
        $attempts = [];
        $rowsBeforeFailure = null;
        $scoped->listen($event, function (WebbliotecaActivity $activity) use (&$attempts, &$rowsBeforeFailure): void {
            $attempts[] = (string) $activity->external_id;
            if ((string) $activity->external_id === '2') {
                $rowsBeforeFailure = WebbliotecaActivity::count();
                throw new RuntimeException('Simulated second-row storage failure');
            }
        });
        WebbliotecaActivity::setEventDispatcher($scoped);

        try {
            app(WebbliotecaService::class)->sync();
        } finally {
            $scoped->forget($event);
            WebbliotecaActivity::setEventDispatcher($original);
        }

        $this->assertSame(['1', '2'], $attempts);
        $this->assertSame(1, $rowsBeforeFailure);
        $this->assertDatabaseCount('webblioteca_activities', 0);
        $this->assertSame($original, WebbliotecaActivity::getEventDispatcher());
        $this->persistActivity(2);
        $this->assertDatabaseCount('webblioteca_activities', 1);
        $this->assertSame(['1', '2'], $attempts);
    }

    public function test_cooldown_skips_requests_then_empty_retry_is_idempotent(): void
    {
        $this->freezeTime();
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['data' => [self::activity(1)]])
            ->push(['data' => []])]);
        $service = app(WebbliotecaService::class);

        $service->sync();
        $existing = WebbliotecaActivity::sole()->getAttributes();
        $service->sync();
        $this->travel(19)->seconds();
        $service->sync();
        Http::assertSentCount(1);
        $this->travel(2)->seconds();
        $service->sync();

        Http::assertSentCount(2);
        $this->assertDatabaseCount('webblioteca_activities', 1);
        $this->assertSame($existing, WebbliotecaActivity::sole()->getAttributes());
        $this->assertSame(['0', '1'], Http::recorded()->map(fn ($pair) => $pair[0]['since_id'])->all());
    }

    public function test_backlog_larger_than_fifty_continues_from_persisted_cursor(): void
    {
        $this->freezeTime();
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['data' => array_map(fn ($id) => self::activity($id), range(1, 50))])
            ->push(['data' => array_map(fn ($id) => self::activity($id), range(51, 75))])
            ->push(['data' => []])]);
        $service = app(WebbliotecaService::class);

        $service->sync();
        $this->assertDatabaseCount('webblioteca_activities', 50);
        $this->travel(21)->seconds();
        $service->sync();
        $this->assertDatabaseCount('webblioteca_activities', 75);
        $this->travel(21)->seconds();
        $service->sync();

        Http::assertSentCount(3);
        $this->assertSame(['0', '50', '75'], Http::recorded()->map(fn ($pair) => $pair[0]['since_id'])->all());
        $this->assertSame(range(1, 75), WebbliotecaActivity::orderBy('external_id')->pluck('external_id')->all());
    }

    private static function activity(int|string $id, array $overrides = []): array
    {
        return array_replace([
            'id' => $id,
            'type' => 'emprestimo.criado',
            'summary' => ['livro' => 'Livro de teste'],
            'actor' => ['id' => 42, 'name' => 'Private Actor', 'email' => 'private@example.test'],
            'created_at' => '2026-10-07T09:00:00-03:00',
        ], $overrides);
    }

    private function persistActivity(int $id): WebbliotecaActivity
    {
        return WebbliotecaActivity::create([
            'external_id' => $id,
            'type' => 'emprestimo.criado',
            'summary' => ['livro' => 'Original'],
            'is_system' => false,
            'occurred_at' => '2026-10-07T12:00:00Z',
        ]);
    }

    private function assertNoWrites(): void
    {
        $writes = array_filter(DB::getQueryLog(), fn ($query) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $query['query']));
        $this->assertSame([], array_values($writes), 'Invalid or empty responses must not issue any database write.');
    }
}
