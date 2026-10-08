@php
  $case = match (strtolower($project['name'])) {
    'hidrauboa-site' => [
      'title' => 'Seu catálogo a um passo da conversa.',
      'description' => 'Na Hidrauboa, uma página institucional reúne produtos e caminhos de atendimento. Uma referência de como apresentar seu negócio e facilitar o próximo contato.',
      'points' => ['Catálogo visual para explorar os produtos', 'Contato direcionado pelo WhatsApp', 'Navegação responsiva e estrutura preparada para buscadores'],
      'note' => 'Site institucional e catálogo para uma empresa de hidráulica em Boa Esperança.',
    ],
    'webblioteca' => [
      'title' => 'Suas regras de negócio, organizadas em um sistema.',
      'description' => 'Quem pode reservar? O que está disponível? Como evitar dois agendamentos no mesmo horário? Na Webblioteca, essas perguntas viram regras de uma aplicação.',
      'points' => ['Acessos e permissões para diferentes perfis', 'Empréstimos e reservas com controle de disponibilidade', 'API para conectar informações a outras aplicações'],
      'note' => 'Projeto de portfólio para demonstrar autenticação, modelagem e regras de negócio.',
    ],
    default => null,
  };
@endphp
<article class="project-card{{ $case ? ' project-featured' : '' }}">
  <div class="project-overview">
    <div class="project-identity"><span class="project-symbol" aria-hidden="true">&lt;/&gt;</span><h3><a href="{{ $project['html_url'] }}">{{ $project['name'] }}</a></h3>@if (!empty($project['language']))<span class="badge">{{ $project['language'] }}</span>@endif</div>
    <div class="project-copy">
      @if ($case)
        <h4>{{ $case['title'] }}</h4>
        <p>{{ $case['description'] }}</p>
        <ul class="project-benefits">@foreach ($case['points'] as $point)<li>{{ $point }}</li>@endforeach</ul>
        <p class="project-note">{{ $case['note'] }}</p>
        <a class="text-link" href="https://www.instagram.com/nathanluceat/">Quero conversar sobre uma solução assim</a>
      @else
        <p>{{ $project['description'] ?: 'Conheça a implementação e a documentação deste projeto no GitHub.' }}</p>
      @endif
      <div class="project-links">
        <a class="text-link" href="{{ $project['html_url'] }}">Ver repositório</a>
        @if (!empty($project['homepage']) && preg_match('~^https?://~i', $project['homepage']))
          <a class="text-link" href="{{ $project['homepage'] }}">Visitar projeto</a>
        @endif
      </div>
    </div>
  </div>
  <details class="project-documentation" data-readme-url="/projetos/{{ rawurlencode($project['name']) }}/readme">
    <summary aria-controls="project-readme-{{ $projectIndex }}">Ler documentação <span>README de {{ $project['name'] }}</span></summary>
    <div class="project-documentation-body" id="project-readme-{{ $projectIndex }}">
      <p class="readme-status" role="status">A documentação será carregada ao abrir esta seção.</p>
      <button class="button button-outline readme-retry" type="button" hidden>Tentar novamente</button>
      <div class="readme-content" data-project-readme></div>
      <a class="text-link" href="{{ $project['html_url'] }}#readme">Ler README no GitHub</a>
      <noscript><p>Use o link acima para ler a documentação no GitHub, ou ative o JavaScript para ler aqui.</p></noscript>
    </div>
  </details>
</article>
