<?php

namespace Tests\Feature;

use App\Models\WebbliotecaActivity;
use App\Services\GitHubService;
use App\Services\WebbliotecaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
    }

    public function test_the_application_returns_a_successful_response(): void
    {
        Http::preventStrayRequests();
        $this->mock(GitHubService::class, function ($mock) {
            $mock->shouldReceive('profile')->once()->andReturn(null);
            $mock->shouldReceive('repos')->once()->andReturn([]);
            $mock->shouldReceive('recentCommits')->once()->andReturn([]);
            $mock->shouldReceive('profileReadmeHtml')->once()->andReturn(null);
        });
        $this->mock(WebbliotecaService::class, function ($mock) {
            $mock->shouldReceive('sync')->once();
        });

        foreach (range(1, 8) as $id) {
            WebbliotecaActivity::create([
                'external_id' => $id,
                'type' => 'emprestimo.criado',
                'summary' => ['livro' => 'Livro '.$id],
                'is_system' => false,
                'occurred_at' => now(),
            ]);
        }

        $response = $this->get('/')->assertStatus(200);
        $response->assertViewHas('activities', fn ($activities) => $activities->pluck('external_id')->all() === [8, 7, 6, 5, 4, 3]);
        $this->assertSame(6, substr_count($response->getContent(), 'class="activity-card"'));
        $this->assertDatabaseCount('webblioteca_activities', 8);
        Http::assertNothingSent();
    }
}
