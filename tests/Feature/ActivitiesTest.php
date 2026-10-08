<?php

namespace Tests\Feature;

use App\Models\WebbliotecaActivity;
use App\Services\WebbliotecaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActivitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        // Configure isolation before the trait runs migrations, not after parent::setUp().
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
            'app.timezone' => 'UTC',
        ]);
        Http::preventStrayRequests();
        Http::fake();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Cache::flush();
    }

    public function test_missing_cursor_defaults_to_zero_with_an_empty_json_list(): void
    {
        $this->mock(WebbliotecaService::class)->shouldReceive('sync')->once();

        $this->getJson('/atividades')->assertOk()->assertExactJson([
            'items' => [],
            'next_since_id' => '0',
        ]);

        Http::assertNothingSent();
    }

    public function test_more_than_twenty_ids_are_paginated_without_skipping_and_each_page_is_descending(): void
    {
        $this->mock(WebbliotecaService::class)->shouldReceive('sync')->times(4);
        // Insert out of order so the contract cannot accidentally depend on local PK order.
        foreach (array_reverse(range(1, 47)) as $id) {
            $this->activity($id);
        }

        $cursor = '0';
        $seen = [];
        foreach ([range(20, 1), range(40, 21), range(47, 41), []] as $expected) {
            $response = $this->getJson('/atividades?since_id='.$cursor)->assertOk();
            $items = $response->json('items');
            $this->assertTrue(array_is_list($items), 'Reversed rows must be reindexed into a JSON list.');
            $this->assertSame(array_map('strval', $expected), array_column($items, 'id'));
            $cursor = $expected === [] ? $cursor : (string) $expected[0];
            $this->assertSame($cursor, $response->json('next_since_id'));
            $seen = array_merge($seen, array_column($items, 'id'));
        }

        $this->assertCount(47, array_unique($seen));
        sort($seen, SORT_NUMERIC);
        $this->assertSame(array_map('strval', range(1, 47)), $seen);
        Http::assertNothingSent();
    }

    public function test_large_ids_and_cursor_are_exact_strings_in_a_descending_reindexed_list(): void
    {
        $this->mock(WebbliotecaService::class)->shouldReceive('sync')->twice();
        foreach (['9007199254740995', '9007199254740993', '9007199254740994'] as $id) {
            $this->activity($id);
        }

        $response = $this->getJson('/atividades?since_id=9007199254740993')->assertOk();
        $this->assertTrue(array_is_list($response->json('items')));
        $this->assertSame(['9007199254740995', '9007199254740994'], array_column($response->json('items'), 'id'));
        $this->assertSame('9007199254740995', $response->json('next_since_id'));
        $this->assertStringContainsString('"items":[', $response->getContent());
        $this->assertStringContainsString('"id":"9007199254740995"', $response->getContent());
        $this->getJson('/atividades?since_id=9007199254740995')->assertOk()->assertExactJson([
            'items' => [],
            'next_since_id' => '9007199254740995',
        ]);
        Http::assertNothingSent();
    }

    public function test_maximum_signed_bigint_cursor_is_preserved_without_float_conversion(): void
    {
        $this->mock(WebbliotecaService::class)->shouldReceive('sync')->once();

        $this->getJson('/atividades?since_id=9223372036854775807')->assertOk()->assertExactJson([
            'items' => [],
            'next_since_id' => '9223372036854775807',
        ]);
        Http::assertNothingSent();
    }

    #[DataProvider('invalidCursors')]
    public function test_invalid_cursor_returns_validation_error_before_sync(string $query): void
    {
        $this->mock(WebbliotecaService::class)->shouldNotReceive('sync');

        $this->getJson('/atividades?'.$query)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('since_id');

        $this->assertDatabaseCount('webblioteca_activities', 0);
        Http::assertNothingSent();
    }

    public static function invalidCursors(): array
    {
        return [
            'empty' => ['since_id='],
            'negative' => ['since_id=-1'],
            'decimal' => ['since_id=1.5'],
            'exponential' => ['since_id=1e3'],
            'letters' => ['since_id=abc'],
            'null word' => ['since_id=null'],
            'boolean word' => ['since_id=true'],
            'plus sign' => ['since_id=%2B1'],
            'leading zeros' => ['since_id=01'],
            'overflow signed integer' => ['since_id=9223372036854775808'],
            'overflow unsigned integer' => ['since_id=18446744073709551616'],
            'huge number' => ['since_id='.str_repeat('9', 100)],
            'array' => ['since_id%5B%5D=1'],
            'associative array' => ['since_id%5Bid%5D=1'],
            'nested array' => ['since_id%5B0%5D%5Bid%5D=1'],
            'newline inside number' => ['since_id=1%0A2'],
            'SQL fragment' => ['since_id=0%20OR%201%3D1'],
        ];
    }

    public function test_response_only_exposes_public_fields_and_anonymous_actor_text(): void
    {
        $this->mock(WebbliotecaService::class)->shouldReceive('sync')->once();
        $this->activity(1, false);
        $this->activity(2, true);

        $response = $this->getJson('/atividades?since_id=0')->assertOk();
        $response->assertExactJson([
            'items' => [
                ['id' => '2', 'text' => 'Sistema emprestou “Livro de teste”', 'at' => '2026-10-07T12:00:00+00:00'],
                ['id' => '1', 'text' => 'Um usuário emprestou “Livro de teste”', 'at' => '2026-10-07T12:00:00+00:00'],
            ],
            'next_since_id' => '2',
        ]);
        foreach (['Private Actor', 'private@example.test', 'actor', 'summary', 'is_system', 'external_id'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        Http::assertNothingSent();
    }

    private function activity(int|string $id, bool $system = false): void
    {
        WebbliotecaActivity::create([
            'external_id' => $id,
            'type' => 'emprestimo.criado',
            'summary' => [
                'livro' => 'Livro de teste',
                'actor' => ['id' => 42, 'name' => 'Private Actor', 'email' => 'private@example.test'],
            ],
            'is_system' => $system,
            'occurred_at' => '2026-10-07T12:00:00Z',
        ]);
    }
}
