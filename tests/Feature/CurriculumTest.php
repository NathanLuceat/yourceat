<?php

namespace Tests\Feature;

use App\Services\GitHubService;
use App\Services\WebbliotecaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CurriculumTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array']);
        Http::preventStrayRequests();
        $this->mock(GitHubService::class, function ($mock) {
            foreach (['profile', 'repos', 'recentCommits', 'profileReadmeHtml', 'projectReadme'] as $method) {
                $mock->shouldNotReceive($method);
            }
        });
        $this->mock(WebbliotecaService::class)->shouldNotReceive('sync');
    }

    public function test_curriculum_is_directly_accessible_without_integrations(): void
    {
        $this->assertSame('/curriculo', route('curriculo', [], false));
        $this->get('/curriculo')->assertOk()->assertViewIs('curriculo')
            ->assertSee('Nathan Rodrigues Oliveira')
            ->assertSee('Sisponto Sistemas Inteligentes')
            ->assertSee('Graduação em andamento')
            ->assertSee('Inatel')
            ->assertSee('Colégio Padre Júlio Maria')
            ->assertSee('Fevereiro de 2023 a dezembro de 2025')
            ->assertSee('primeira oportunidade profissional em uma equipe de desenvolvimento')
            ->assertSee('C2 (autoavaliação)')
            ->assertSee('2º lugar em 2024, 3º lugar em 2025 e 19º lugar em 2023')
            ->assertSee('mailto:nathanluceat@gmail.com')
            ->assertSee('tel:+5535988933911')
            ->assertSee('https://github.com/NathanLuceat/webblioteca')
            ->assertSee('https://github.com/NathanLuceat/hidrauboa-site')
            ->assertSee('https://github.com/NathanLuceat/yourceat')
            ->assertSee('href="/">Voltar ao site', false)
            ->assertSee('css/portfolio.css')
            ->assertDontSee('js/activities.js')
            ->assertDontSee('js/projects.js');
        Http::assertNothingSent();
    }

    public function test_public_curriculum_omits_old_contact_and_private_details(): void
    {
        $response = $this->get('/curriculo')->assertOk();
        $text = strip_tags($response->getContent());

        $this->assertDoesNotMatchRegularExpression('/\b(?:Rua|CEP|Nascimento)\b/ui', $text);
        $this->assertDoesNotMatchRegularExpression('/\b\d{2}\/\d{2}\/\d{4}\b/', $text);
        preg_match_all('/mailto:([^"\s]+)/', $response->getContent(), $emails);
        $this->assertSame(['nathanluceat@gmail.com'], array_values(array_unique($emails[1])));
        Http::assertNothingSent();
    }
}
