<?php

namespace Tests\Feature;

use App\Models\WebbliotecaActivity;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestView;
use Tests\TestCase;

class PortfolioViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function portfolio(array $overrides = []): TestView
    {
        // Renderização direta: não chama controllers, APIs nem banco de dados.
        return $this->view('home', array_replace([
            'profile' => null,
            'projects' => collect(),
            'commits' => collect(),
            'readme' => null,
            'activities' => collect(),
        ], $overrides));
    }

    public function test_portfolio_renders_without_external_data(): void
    {
        $this->portfolio()
            ->assertSee('Nathan Luceat')
            ->assertSee('problemas são ouro!')
            ->assertSee('Os projetos não estão disponíveis nesta consulta.')
            ->assertSee('Sem commits disponíveis nesta consulta.')
            ->assertSee('README indisponível nesta consulta.')
            ->assertSee('id="webblioteca-empty"', false)
            ->assertSee('data-next-since-id="0"', false)
            ->assertSee('css/portfolio.css')
            ->assertSee('js/activities.js')
            ->assertSee('href="#conteudo"', false)
            ->assertSee('https://www.instagram.com/nathanluceat/')
            ->assertSee('mailto:nathanluceat@gmail.com')
            ->assertSee('https://wa.me/5535988933911')
            ->assertSee('https://webblioteca.laravel.cloud');
    }

    public function test_portfolio_renders_github_data_and_sanitized_readme(): void
    {
        $this->portfolio([
            'profile' => [
                'avatar_url' => 'https://example.test/avatar.png',
                'html_url' => 'https://github.com/example',
            ],
            'projects' => collect([[
                'name' => 'Projeto de teste',
                'description' => str_repeat('Descrição longa de teste. ', 30),
                'language' => 'PHP',
                'html_url' => 'https://github.com/example/project',
                'homepage' => 'https://example.test/project',
            ]]),
            'commits' => collect([[
                'message' => 'Mensagem de teste',
                'repo' => 'example/project',
                'date' => '2026-10-07T12:00:00Z',
                'url' => 'https://github.com/example/project/commit/123',
            ]]),
            'readme' => '<p>README já sanitizado <strong>pelo serviço</strong>.</p>',
        ])
            ->assertSee('https://example.test/avatar.png')
            ->assertSee('Projeto de teste')
            ->assertSee(str_repeat('Descrição longa de teste. ', 30))
            ->assertSee('PHP')
            ->assertSee('href="https://example.test/project"', false)
            ->assertSee('Mensagem de teste')
            ->assertSee('07/10/2026')
            ->assertSee('<strong>pelo serviço</strong>', false);
    }

    public function test_repository_text_is_escaped_and_unsafe_homepage_is_not_linked(): void
    {
        $this->portfolio([
            'projects' => collect([[
                'name' => '<script>alert(1)</script>',
                'description' => '<img src=x onerror=alert(1)>',
                'language' => null,
                'html_url' => 'https://github.com/example/project',
                'homepage' => 'javascript:alert(1)',
            ]]),
        ])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false);
    }

    public function test_personal_readme_is_always_visible_before_projects(): void
    {
        $html = (string) $this->portfolio(['readme' => '<p>Trajetória completa</p>']);
        $this->assertLessThan(strpos($html, 'id="projetos"'), strpos($html, 'id="readme"'));
        $this->assertStringNotContainsString('<details', $html);
        $this->assertStringContainsString('Trajetória completa', $html);
        $this->assertStringContainsString('Santa Rita do Sapucaí', $html);
        $this->assertStringContainsString('Seu programador, a um direct de distância.', $html);
        $this->assertStringContainsString('Seu programador. Por Nathan Luceat.', $html);
        $this->assertStringContainsString('href="/curriculo"', $html);
        $this->assertStringContainsString('Quero entender o que seu negócio precisa', $html);
        $this->assertStringNotContainsString('Eu entro com o código —', $html);
        $this->assertStringContainsString('class="brand-photo"', $html);
        $this->assertStringContainsString('js/projects.js', $html);
        Http::assertNothingSent();
    }

    public function test_cases_only_appear_for_corresponding_projects_with_lazy_documentation(): void
    {
        $projects = collect(['hidrauboa-site', 'webblioteca'])->map(fn ($name) => [
            'name' => $name,
            'description' => 'Descrição da API',
            'language' => 'PHP',
            'html_url' => 'https://github.com/NathanLuceat/'.$name,
            'homepage' => null,
        ]);
        $this->portfolio(['projects' => $projects])
            ->assertSee('Seu catálogo a um passo da conversa.')
            ->assertSee('Suas regras de negócio, organizadas em um sistema.')
            ->assertSee('Ler documentação')
            ->assertSee('/projetos/hidrauboa-site/readme')
            ->assertSee('/projetos/webblioteca/readme');
        $this->portfolio()->assertDontSee('Seu catálogo a um passo da conversa.');
        Http::assertNothingSent();
    }

    public function test_repository_description_can_be_null(): void
    {
        $this->portfolio([
            'projects' => collect([[
                'name' => 'Projeto sem descrição',
                'description' => null,
                'language' => null,
                'html_url' => 'https://github.com/example/project',
                'homepage' => null,
            ]]),
        ])->assertSee('Conheça a implementação e a documentação deste projeto no GitHub.');
    }

    public function test_activity_cards_preserve_large_cursor_and_escape_remote_text(): void
    {
        $activity = new WebbliotecaActivity([
            'external_id' => '9007199254740993',
            'type' => 'emprestimo.criado',
            'summary' => ['livro' => '<script>alert(1)</script>'],
            'is_system' => false,
            'occurred_at' => '2026-10-07T12:00:00Z',
        ]);

        $this->portfolio(['activities' => collect([$activity])])
            ->assertSee('data-next-since-id="9007199254740993"', false)
            ->assertSee('class="activity-card" data-id="9007199254740993"', false)
            ->assertSee('class="activity-mark"', false)
            ->assertSee('class="activity-text"', false)
            ->assertSee('class="activity-time" datetime=', false)
            ->assertSee('Um usuário emprestou')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('id="webblioteca-empty"', false);
    }
}
