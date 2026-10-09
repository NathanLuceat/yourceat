<?php

namespace Tests\Feature;

use App\Services\WebbliotecaService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebbliotecaCommandTest extends TestCase
{
    #[DataProvider('results')]
    public function test_command_reports_result_and_exit_code(string $status, int $count, int $exit): void
    {
        $this->mock(WebbliotecaService::class, function (MockInterface $mock) use ($status, $count) {
            $mock->shouldReceive('sync')->once()->andReturn(['status' => $status, 'count' => $count]);
        });

        $this->artisan('webblioteca:sync')
            ->expectsOutput("Webblioteca: {$status} ({$count})")
            ->assertExitCode($exit);
    }

    public static function results(): array
    {
        return [
            'success' => ['synced', 50, 0],
            'empty' => ['synced', 0, 0],
            'skipped' => ['skipped', 0, 0],
            'failed' => ['failed', 0, 1],
        ];
    }

    public function test_command_uses_the_same_lock_as_http_service(): void
    {
        config([
            'cache.default' => 'array',
            'services.webblioteca.url' => 'https://webblioteca.example.test',
            'services.webblioteca.token' => 'secret',
        ]);
        Http::preventStrayRequests();
        Http::fake();
        $lock = Cache::lock('webblioteca:sync:lock', 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('webblioteca:sync')->expectsOutput('Webblioteca: skipped (0)')->assertSuccessful();
            $this->assertTrue($lock->isOwnedByCurrentProcess());
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_invalid_configuration_fails_without_logging_secrets(): void
    {
        config(['services.webblioteca.url' => 'invalid-private-url', 'services.webblioteca.token' => 'secret']);
        Log::spy();
        Http::fake();

        $this->artisan('webblioteca:sync')->expectsOutput('Webblioteca: failed (0)')->assertExitCode(1);

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->once()->with('Webblioteca sync failed', [
            'stage' => 'configuration', 'http_status' => null,
        ]);
    }

    public function test_schedule_runs_hourly_on_one_server_without_overlap_for_five_minutes(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'webblioteca:sync'));

        $this->assertCount(1, $events);
        $event = $events->sole();
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->onOneServer);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(5, $event->expiresAt);
    }
}
