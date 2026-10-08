<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Seu programador, a um direct de distância. Nathan Luceat desenvolve sites, catálogos e sistemas sob medida. Boa Esperança, Santa Rita do Sapucaí e região, com abertura para novos lugares.">
  <meta name="theme-color" content="#141816">
  <title>Nathan Luceat | Seu programador | Yourceat</title>
  <link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|fraunces:400,500,600,700&amp;display=swap">
  <link rel="stylesheet" href="{{ asset('css/portfolio.css') }}">
  @if (!empty($profile['avatar_url']))
    <link rel="icon" href="{{ $profile['avatar_url'] }}">
  @endif
</head>
<body>
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <header class="site-header shell">
    <a class="wordmark" href="#perfil" aria-label="Yourceat, início">
      <span class="brand-photo"><span aria-hidden="true">NL</span>@if (!empty($profile['avatar_url']))<img src="{{ $profile['avatar_url'] }}" alt="" width="48" height="48">@endif</span>
      <span>yourceat<small>Seu Luceat.</small></span>
    </a>
    <nav aria-label="Navegação principal">
      <a href="#solucoes">Soluções</a>
      <a href="#sobre">Sobre</a>
      <a href="#projetos">Projetos</a>
      <a href="{{ route('curriculo', [], false) }}">Currículo</a>
      <a class="nav-contact" href="#contato">Vamos conversar</a>
    </nav>
  </header>

  <main id="conteudo">
    <section id="perfil" class="hero shell" aria-labelledby="hero-title">
      <div class="hero-copy">
        <p class="byline">Nathan Luceat <span>Desenvolvimento full-stack</span></p>
        <h1 id="hero-title">Seu programador, a um direct de distância.</h1>
        <p class="hero-intro">Quero entender o que seu negócio precisa e desenvolver uma solução que faça sentido na prática, seja um site, um sistema ou a melhoria de um processo.</p>
        <div class="actions">
          <a class="button button-primary" href="https://www.instagram.com/nathanluceat/">Vamos conversar no Instagram</a>
          <a class="button button-outline" href="#projetos">Conheça meus projetos</a>
        </div>
        <p class="hero-footnote">Boa Esperança, Santa Rita do Sapucaí e região.<br>De Minas, com vontade de expandir fronteiras.</p>
      </div>
      <aside class="maker-note" aria-label="Quem vai construir com você">
        <div class="portrait-frame">
          <span class="portrait-fallback" aria-hidden="true">NL</span>
          @if (!empty($profile['avatar_url']))
            <img src="{{ $profile['avatar_url'] }}" alt="Foto de perfil de Nathan Luceat no GitHub" width="256" height="256" fetchpriority="high">
          @endif
        </div>
        <p class="maker-name">Nathan Luceat</p>
        <p>Uma pessoa para ouvir sua ideia.<br>E trabalhar com você para construí-la.</p>
        <blockquote>“Para um engenheiro,<br>problemas são ouro!”</blockquote>
      </aside>
    </section>

    <section id="solucoes" class="solutions-section" aria-labelledby="solutions-title">
      <div class="shell section">
        <div class="section-heading">
          <h2 id="solutions-title">O que posso construir<br>com você.</h2>
          <p>Da vitrine do seu negócio às regras que fazem sua operação funcionar.</p>
        </div>
        <div class="solutions-grid">
          <article><svg viewBox="0 0 40 40" fill="none" aria-hidden="true"><rect x="4" y="7" width="32" height="26" rx="2"/><path d="M4 14h32M10 10.5h1m4 0h1M11 21h18M11 26h11"/></svg><h3>Sites e catálogos</h3><p>Apresente sua empresa, organize seus produtos e dê ao cliente um caminho claro para entrar em contato.</p><a class="text-link" href="#projetos">Conheça as soluções nos projetos</a></article>
          <article><svg viewBox="0 0 40 40" fill="none" aria-hidden="true"><rect x="5" y="5" width="12" height="12" rx="2"/><rect x="23" y="23" width="12" height="12" rx="2"/><path d="M23 11h6v12M17 29h-6V17M26 20l3 3 3-3"/></svg><h3>Sistemas para processos</h3><p>Transforme regras, cadastros e reservas em uma aplicação pensada para a rotina de quem vai usar.</p><a class="text-link" href="#webblioteca">Veja a Webblioteca em atividade</a></article>
          <article><svg viewBox="0 0 40 40" fill="none" aria-hidden="true"><path d="M15 12h-4a8 8 0 000 16h4M25 12h4a8 8 0 010 16h-4M12 20h16"/><circle cx="20" cy="20" r="5"/></svg><h3>Integrações entre aplicações</h3><p>Conecte informações entre sistemas por APIs, com atenção ao acesso, à privacidade e às falhas externas.</p><a class="text-link" href="https://www.instagram.com/nathanluceat/">Conte o que precisa conectar</a></article>
        </div>
      </div>
    </section>

    <section id="sobre" class="section shell" aria-labelledby="about-title">
      <div class="about">
        <div><p class="section-caption">Quem está do outro lado</p><h2 id="about-title">Entender primeiro.<br>Construir com propósito.</h2></div>
        <div class="about-story">
          <p>Sou Nathan Luceat, estudante de Engenharia de Software no Inatel. Minha prática em programação começou há mais de cinco anos, entre estudos, projetos próprios e competições desde o ensino fundamental.</p>
          <p>Hoje trabalho com suporte técnico na Sisponto Sistemas Inteligentes e desenvolvo landing pages como freelancer. Gosto de aproximar o lado técnico das necessidades de quem usa o sistema.</p>
          <p>Atuo em Boa Esperança, Santa Rita do Sapucaí e região, e busco expandir minhas fronteiras. Podemos conversar em português ou inglês, inclusive à distância.</p>
        </div>
      </div>
      <div id="readme" class="readme-panel">
        <div class="readme-heading"><div><h3>Meu caderno aberto.</h3><p>Trajetória, estudos e competições, direto do README do meu perfil.</p></div><a class="text-link" href="{{ $profile['html_url'] ?? 'https://github.com/NathanLuceat' }}">Visitar perfil no GitHub</a></div>
        @if ($readme)
          <div class="readme-content">{{-- HTML sanitizado pelo serviço, sempre visível. --}}{!! $readme !!}</div>
        @else
          <p class="muted">README indisponível nesta consulta.</p>
        @endif
      </div>
    </section>

    <section id="projetos" class="section shell projects-section" aria-labelledby="projects-title">
      <div class="section-heading">
        <div><p class="section-caption">Do código para a vida real</p><h2 id="projects-title">Soluções que já<br>saíram do papel.</h2></div>
        <p>Veja o que construí, o que isso pode inspirar no seu negócio e a documentação por trás de cada projeto.</p>
      </div>
      <div class="projects-list">
        @forelse ($projects as $p)
          @include('partials.project', ['project' => $p, 'projectIndex' => $loop->index])
        @empty
          <div class="empty-state"><p>Os projetos não estão disponíveis nesta consulta.</p><a class="text-link" href="https://github.com/NathanLuceat?tab=repositories">Explore meus repositórios no GitHub</a></div>
        @endforelse
      </div>
    </section>

    <section id="webblioteca" class="library-section" data-next-since-id="{{ $activities->max('external_id') ?? '0' }}" aria-labelledby="library-title">
      <div class="shell library-inner">
        <div class="library-heading">
          <div><p class="library-caption">Um dos meus projetos, de perto</p><h2 id="library-title">We<span>BB</span>lioteca</h2></div>
          <a class="library-button" href="https://webblioteca.laravel.cloud">Abrir a Webblioteca</a>
        </div>
        <div class="library-intro">
          <p>Livros, encontros e conhecimento.<br>Uma biblioteca também pode começar com código.</p>
          <p>Empréstimos de livros, reservas de salas e regras de disponibilidade. A integração abaixo mostra como dois sistemas podem conversar sem expor a identidade de quem usa.</p>
        </div>
        <div class="catalog-heading"><h3>As seis atividades mais recentes</h3><p id="webblioteca-status" role="status">Consulta da lista a cada 30 segundos</p></div>
        <ul id="webblioteca-list" class="activity-grid">
          @forelse ($activities as $a)
            <li class="activity-card" data-id="{{ $a->external_id }}">
              <span class="activity-mark" aria-hidden="true">W</span>
              <p class="activity-text">{{ $a->text }}</p>
              <time class="activity-time" datetime="{{ $a->occurred_at->toIso8601String() }}">{{ $a->occurred_at->format('d/m H:i') }}</time>
            </li>
          @empty
            <li id="webblioteca-empty" class="library-empty">Nenhuma atividade disponível nesta consulta. Você pode conhecer o projeto pelo link acima.</li>
          @endforelse
        </ul>
        <noscript><p>Esta é a lista salva no momento da abertura da página. Ative o JavaScript para consultar novas atividades automaticamente.</p></noscript>
        <p class="library-privacy">Últimas atividades recebidas. Histórias de uso, identidades preservadas. Nomes de usuários não são exibidos.</p>
      </div>
    </section>

    <section id="commits" class="section shell" aria-labelledby="commits-title">
      <div class="section-heading"><div><p class="section-caption">O trabalho continua</p><h2 id="commits-title">Últimos commits</h2></div><p>Pequenos registros do que vem sendo construído, direto do GitHub.</p></div>
      <ul class="commit-list">
        @forelse ($commits as $c)
          <li class="commit-card"><a href="{{ $c['url'] }}"><span class="commit-meta"><span>{{ $c['repo'] }}</span><time datetime="{{ $c['date'] }}">{{ \Carbon\Carbon::parse($c['date'])->format('d/m/Y') }}</time></span><span class="commit-message">{{ $c['message'] }}</span><span class="commit-code">{{ $c['sha'] ?? 'Ver alteração' }}</span></a></li>
        @empty
          <li class="empty-state"><p>Sem commits disponíveis nesta consulta.</p></li>
        @endforelse
      </ul>
    </section>

    <section id="contato" class="contact-section" aria-labelledby="contact-title">
      <div class="shell contact-inner">
        <div><p class="section-caption">Seu Luceat, no seu próximo projeto</p><h2 id="contact-title">Qual problema<br>vamos resolver?</h2><p>Me conte sobre seu negócio, sua ideia e o que precisa funcionar melhor. A primeira conversa é para entender o que podemos construir juntos.</p><p class="contact-region">Boa Esperança, Santa Rita do Sapucaí e região.<br>Aberto a novos lugares e projetos à distância.</p></div>
        <div class="contact-links">
          <a class="contact-primary" href="https://www.instagram.com/nathanluceat/"><span>Converse comigo no Instagram</span><strong>@nathanluceat</strong><span class="contact-invite">Meu principal canal de contato</span></a>
          <a href="mailto:nathanluceat@gmail.com"><span>E-mail</span><strong>nathanluceat@gmail.com</strong></a>
          <a href="https://wa.me/5535988933911"><span>WhatsApp</span><strong>+55 (35) 98893-3911</strong></a>
        </div>
      </div>
    </section>
  </main>
  <footer class="site-footer shell"><p>Yourceat <span>Seu programador. Por Nathan Luceat.</span></p><p>Construído com curiosidade, código e propósito.</p><a href="#perfil">Voltar ao início</a></footer>
  <script src="{{ asset('js/activities.js') }}" defer></script>
  <script src="{{ asset('js/projects.js') }}" defer></script>
</body>
</html>
