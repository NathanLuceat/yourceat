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
            ->assertSee('Desenvolvedor Backend | PHP · Laravel · MySQL')
            ->assertSee('Sisponto Sistemas Inteligentes')
            ->assertSee('helpdesk')
            ->assertSee('Utilização pontual de consultas SQL em bancos MySQL')
            ->assertSee('Graduação em andamento')
            ->assertSee('Instituto Nacional de Telecomunicações — Inatel')
            ->assertSee('Colégio Padre Júlio Maria')
            ->assertSee('Fevereiro de 2023 a dezembro de 2025')
            ->assertSee('oportunidades de estágio ou desenvolvimento backend júnior')
            ->assertSee('C2 (autoavaliação; sem certificação internacional)')
            ->assertSee('2º lugar em 2024, 3º lugar em 2025 e 19º lugar em 2023')
            ->assertSee('Torneio Brasil de Robótica')
            ->assertSee('mailto:nathanluceat@gmail.com')
            ->assertSee('tel:+5535988933911')
            ->assertSee('https://github.com/NathanLuceat/webblioteca')
            ->assertSee('https://github.com/NathanLuceat/hidrauboa-site')
            ->assertSee('https://github.com/NathanLuceat/yourceat')
            ->assertSee('href="/">Voltar ao site', false)
            ->assertSee('css/portfolio.css')
            ->assertSee('css/curriculum.css')
            ->assertSee('js/curriculum.js')
            ->assertDontSee('js/activities.js')
            ->assertDontSee('js/projects.js')
            ->assertDontSee('Foco atual de estudos')
            ->assertDontSee('instagram.com');
        Http::assertNothingSent();
    }

    public function test_public_curriculum_omits_private_details_and_original_certificates(): void
    {
        $response = $this->get('/curriculo')->assertOk();
        $text = strip_tags($response->getContent());

        $this->assertDoesNotMatchRegularExpression('/\b(?:Rua|CEP|Nascimento|CPF|RG)\b/ui', $text);
        $this->assertDoesNotMatchRegularExpression('/\b\d{2}\/\d{2}\/\d{4}\b/', $text);
        $this->assertDoesNotMatchRegularExpression('/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b|\b\d{11}\b/', $text);
        $this->assertStringNotContainsString('.pdf', $response->getContent());
        preg_match_all('/mailto:([^"\s]+)/', $response->getContent(), $emails);
        $this->assertSame(['nathanluceat@gmail.com'], array_values(array_unique($emails[1])));
        Http::assertNothingSent();
    }

    public function test_certifications_show_verified_metadata_without_validation_links(): void
    {
        $response = $this->get('/curriculo')->assertOk()
            ->assertSee('Desenvolvimento Backend com PHP: POO e Integração com MySQL')
            ->assertSee('PHP para Iniciantes: Fundamentos Práticos para o Desenvolvimento Web')
            ->assertSee('Banco de Dados 1: fundamentos')
            ->assertSee('Instituto Federal do Rio Grande do Sul (IFRS)')
            ->assertSee('30 horas')
            ->assertSee('20 horas')
            ->assertSee('datetime="2026-11-12"', false)
            ->assertSee('datetime="2026-11-05"', false)
            ->assertDontSee('Validar certificado');

        $this->assertSame(3, substr_count($response->getContent(), 'class="cv-entry cv-certificate"'));
        $this->assertSame(80, array_sum(array_column(config('curriculum.certifications'), 'hours')));
    }

    public function test_sections_and_backend_projects_follow_reading_order(): void
    {
        $this->get('/curriculo')->assertOk()->assertSeeInOrder([
            'id="cv-profile"', 'id="cv-skills"', 'id="cv-experience"',
            'id="cv-projects"', 'id="cv-education"', 'id="cv-certifications"',
            'id="cv-awards"', 'id="cv-languages"',
        ], false)->assertSeeInOrder(['<h3>Webblioteca</h3>', '<h3>Yourceat</h3>', '<h3>Hidrauboa</h3>'], false);
    }

    public function test_metadata_print_control_and_local_assets_are_available(): void
    {
        $this->get('/curriculo')->assertOk()
            ->assertSee('<html lang="pt-BR">', false)
            ->assertSee('rel="canonical" href="https://yourceat.laravel.cloud/curriculo"', false)
            ->assertSee('property="og:url" content="https://yourceat.laravel.cloud/curriculo"', false)
            ->assertSee('id="print-curriculum" hidden', false)
            ->assertSee('href="#curriculo"', false);

        $this->assertFileExists(public_path('css/curriculum.css'));
        $this->assertFileExists(public_path('js/curriculum.js'));
        $css = file_get_contents(public_path('css/curriculum.css'));
        $this->assertStringContainsString('@media print', $css);
        $this->assertStringContainsString('size: A4', $css);
    }

    public function test_empty_certifications_omit_section_and_config_text_is_escaped(): void
    {
        config(['curriculum.certifications' => [], 'curriculum.professional_name' => '<script>alert(1)</script>']);
        $this->get('/curriculo')->assertOk()
            ->assertDontSee('id="cv-certifications"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_certificate_validation_link_requires_https(): void
    {
        config(['curriculum.certifications.0.validation_url' => 'javascript:alert(1)']);
        $this->get('/curriculo')->assertOk()->assertDontSee('javascript:alert(1)', false);

        config(['curriculum.certifications.0.validation_url' => 'https://example.test/validate']);
        $this->get('/curriculo')->assertOk()
            ->assertSee('href="https://example.test/validate"', false)
            ->assertSee('Validar certificado');
    }
}
