<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Yourceat</title>
</head>
<body>
  <header id="perfil">
    @if ($profile)
      <img src="{{ $profile['avatar_url'] }}" alt="Avatar" width="120">
      <h1>{{ $profile['name'] ?? $profile['login'] }}</h1>
      <p>{{ $profile['bio'] }}</p>
      <ul>
        <li>{{ $profile['followers'] }} seguidores</li>
        <li>{{ $profile['public_repos'] }} repositórios</li>
        <li>{{ $profile['location'] }}</li>
      </ul>
      <a href="{{ $profile['html_url'] }}">Ver no GitHub</a>
    @else
      <p>Não foi possível carregar o perfil agora.</p>
    @endif
  </header>

  <main>
    <section id="commits">
      <h2>Últimos commits</h2>
      <ul>
        @forelse ($commits as $c)
          <li>
            <a href="{{ $c['url'] }}">{{ $c['message'] }}</a>
            <small>{{ $c['repo'] }} · {{ \Carbon\Carbon::parse($c['date'])->diffForHumans() }}</small>
          </li>
        @empty
          <li>Sem commits recentes.</li>
        @endforelse
      </ul>
    </section>

    <section id="webblioteca">
      <h2>Atividade na Webblioteca</h2>
      <ul>
        {{-- ajustar aos campos reais da API --}}
        @forelse ($activities as $a)
          <li>{{ $a['type'] ?? '-' }} · {{ $a['occurred_at'] ?? '' }}</li>
        @empty
          <li>Sem atividade recente.</li>
        @endforelse
      </ul>
    </section>

    <section id="projetos">
      <h2>Projetos</h2>
      @foreach ($projects as $p)
        <article>
          <h3><a href="{{ $p['html_url'] }}">{{ $p['name'] }}</a></h3>
          <p>{{ $p['description'] }}</p>
          <small>{{ $p['language'] }} · ★ {{ $p['stargazers_count'] }}</small>
        </article>
      @endforeach
    </section>

    <section id="readme">
      <h2>README do perfil</h2>
      {{-- sem escape porque o HTML já foi sanitizado no GitHubService --}}
      {!! $readme !!}
    </section>
  </main>
</body>
</html>