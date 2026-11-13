@php($cv = config('curriculum'))
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Currículo de {{ $cv['name'] }} ({{ $cv['professional_name'] }}). Desenvolvimento backend com PHP, Laravel, MySQL e APIs REST. Projetos, experiência e certificações.">
  <meta name="theme-color" content="#10241D">
  <title>{{ $cv['professional_name'] }} | Desenvolvedor Backend PHP e Laravel | Currículo</title>
  <link rel="canonical" href="{{ $cv['portfolio'] }}/curriculo">
  <meta property="og:type" content="profile">
  <meta property="og:locale" content="pt_BR">
  <meta property="og:title" content="{{ $cv['name'] }} | {{ $cv['title'] }}">
  <meta property="og:description" content="{{ $cv['subtitle'] }}. Experiência, projetos backend e certificações.">
  <meta property="og:url" content="{{ $cv['portfolio'] }}/curriculo">
  <link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|fraunces:400,500,600,700&amp;display=swap">
  <link rel="stylesheet" href="{{ asset('css/portfolio.css') }}">
  <link rel="stylesheet" href="{{ asset('css/curriculum.css') }}">
</head>
<body class="cv-page">
  <a class="skip-link" href="#curriculo">Pular para o currículo</a>
  <header class="site-header shell cv-navigation">
    <a class="wordmark" href="/" aria-label="Yourceat, início"><span class="brand-photo" aria-hidden="true">NL<img id="curriculum-avatar" src="https://avatars.githubusercontent.com/u/324915588?v=4&amp;s=96" alt="" width="48" height="48" referrerpolicy="no-referrer"></span><span>yourceat<small>Seu programador.</small></span></a>
    <div class="cv-actions">
      <a class="button button-outline" href="/">Voltar ao site</a>
      <button class="button button-primary" type="button" id="print-curriculum" hidden>Imprimir / salvar PDF</button>
    </div>
  </header>
  <main id="curriculo" class="shell cv-document">
    <header class="cv-heading">
      <p class="cv-public-name">{{ $cv['professional_name'] }}</p>
      <h1>{{ $cv['name'] }}</h1>
      <p class="cv-role">{{ $cv['title'] }}</p>
      <p class="cv-subtitle">{{ $cv['subtitle'] }}</p>
      <p class="cv-location">{{ $cv['location'] }}</p>
      <ul class="cv-contacts" aria-label="Contatos profissionais">
        <li><a href="mailto:{{ $cv['email'] }}">{{ $cv['email'] }}</a></li>
        <li><a href="{{ $cv['phone_uri'] }}">{{ $cv['phone'] }}</a></li>
        <li><a href="{{ $cv['github'] }}">{{ str_replace('https://', '', $cv['github']) }}</a></li>
        <li><a href="{{ $cv['portfolio'] }}">{{ str_replace('https://', '', $cv['portfolio']) }}</a></li>
      </ul>
    </header>

    <section class="cv-section" aria-labelledby="cv-profile">
      <h2 id="cv-profile">Resumo profissional</h2>
      <div class="cv-section-body">
        @foreach ($cv['summary'] as $paragraph)
          <p>{{ $paragraph }}</p>
        @endforeach
      </div>
    </section>

    <section class="cv-section" aria-labelledby="cv-skills">
      <h2 id="cv-skills">Competências técnicas</h2>
      <div class="cv-section-body">
        <dl class="cv-skills">
          @foreach ($cv['skills'] as $group => $skills)
            <div><dt>{{ $group }}</dt><dd>{{ implode(' · ', $skills) }}</dd></div>
          @endforeach
        </dl>
      </div>
    </section>

    <section class="cv-section" aria-labelledby="cv-experience">
      <h2 id="cv-experience">Experiência profissional</h2>
      <div class="cv-section-body">
        @foreach ($cv['experience'] as $experience)
          <article class="cv-entry">
            <h3>{{ $experience['role'] }}</h3>
            <p class="cv-entry-meta">{{ $experience['organization'] }}@if ($experience['period']) · {{ $experience['period'] }}@endif</p>
            <ul>@foreach ($experience['details'] as $detail)<li>{{ $detail }}</li>@endforeach</ul>
          </article>
        @endforeach
      </div>
    </section>

    <section class="cv-section cv-projects-section" aria-labelledby="cv-projects">
      <h2 id="cv-projects">Projetos selecionados</h2>
      <div class="cv-section-body">
        @foreach ($cv['projects'] as $project)
          <article @class(['cv-entry', 'cv-project', 'cv-project-featured' => $project['featured'], 'cv-online-only' => ! $project['featured']])>
            <h3>{{ $project['name'] }}</h3>
            <p>{{ $project['description'] }}</p>
            <p class="cv-technologies">{{ $project['technologies'] }}</p>
            <a href="{{ $project['url'] }}">{{ $project['label'] }}</a>
          </article>
        @endforeach
      </div>
    </section>

    <section class="cv-section" aria-labelledby="cv-education">
      <h2 id="cv-education">Formação acadêmica</h2>
      <div class="cv-section-body">
        @foreach ($cv['education'] as $education)
          <article @class(['cv-entry', 'cv-online-only' => $education['online_only']])>
            <h3>{{ $education['course'] }}</h3>
            <p>{{ $education['institution'] }}</p>
            <p class="cv-entry-meta">{{ $education['status'] }}</p>
          </article>
        @endforeach
      </div>
    </section>

    @if ($cv['certifications'])
      <section class="cv-section" aria-labelledby="cv-certifications">
        <h2 id="cv-certifications">Certificações</h2>
        <div class="cv-section-body cv-certifications">
          @foreach ($cv['certifications'] as $certificate)
            <article class="cv-entry cv-certificate">
              <h3>{{ $certificate['title'] }}</h3>
              <p>{{ $certificate['issuer'] }}</p>
              <p class="cv-entry-meta">{{ $certificate['hours'] }} horas · <time datetime="{{ $certificate['completed_at'] }}">{{ $certificate['year'] }}</time></p>
              @if ($certificate['validation_url'] && \Illuminate\Support\Str::startsWith($certificate['validation_url'], 'https://'))
                <a href="{{ $certificate['validation_url'] }}" aria-label="Validar certificado: {{ $certificate['title'] }}">Validar certificado</a>
              @endif
            </article>
          @endforeach
        </div>
      </section>
    @endif

    <section class="cv-section" aria-labelledby="cv-awards">
      <h2 id="cv-awards">Conquistas</h2>
      <div class="cv-section-body">
        @foreach ($cv['awards'] as $award)
          <article class="cv-entry">
            <h3>{{ $award['title'] }}</h3>
            @if ($award['context'])<p class="cv-entry-meta">{{ $award['context'] }}</p>@endif
            <ul>@foreach ($award['details'] as $detail)<li>{{ $detail }}</li>@endforeach</ul>
          </article>
        @endforeach
      </div>
    </section>

    <section class="cv-section" aria-labelledby="cv-languages">
      <h2 id="cv-languages">Idiomas</h2>
      <div class="cv-section-body">
        @foreach ($cv['languages'] as $language => $level)
          <p><strong>{{ $language }}:</strong> {{ $level }}.</p>
        @endforeach
      </div>
    </section>
  </main>
  <footer class="shell cv-footer">
    <a class="text-link" href="/">Conheça meus projetos no Yourceat</a>
    <p>Para salvar em PDF, use o botão de impressão ou Ctrl + P. A versão A4 prioriza os projetos backend e mantém as três certificações.</p>
  </footer>
  <script src="{{ asset('js/curriculum.js') }}" defer></script>
</body>
</html>
