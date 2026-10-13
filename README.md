# Yourceat

**Seu programador, a um direct de distância.** Meu portfólio de desenvolvimento: sites, catálogos, sistemas para processos e integrações. Uma apresentação do meu trabalho, com projetos do GitHub e atividades da [Webblioteca](https://webblioteca.laravel.cloud), um sistema de biblioteca que também desenvolvo.

- **Site publicado:** [yourceat.laravel.cloud](https://yourceat.laravel.cloud)
- **Currículo:** [yourceat.laravel.cloud/curriculo](https://yourceat.laravel.cloud/curriculo)
- **Código:** [NathanLuceat/yourceat](https://github.com/NathanLuceat/yourceat)

O projeto exercita o consumo de APIs reais, autenticação no servidor, cache, sincronização incremental, tratamento de falhas e privacidade. O frontend usa Blade, CSS e JavaScript puro; não é necessário compilar assets para abrir o portfólio.

## O que você encontra

- Apresentação profissional, serviços, trajetória e contatos de Nathan Luceat.
- README do perfil sempre aberto e documentação dos projetos carregada sob demanda.
- Repositórios públicos próprios, sem forks nem arquivados.
- Até **nove commits**, organizados em uma grade de três colunas em telas largas.
- As **seis atividades mais recentes recebidas** da Webblioteca, com consulta a cada 30 segundos enquanto a aba está visível. O histórico completo recebido permanece no banco.
- Currículo independente das integrações, com estilos para imprimir ou salvar em PDF pelo navegador.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.4 + Laravel 13 |
| Banco local | MySQL 8 |
| Servidor local | Nginx + PHP-FPM |
| Ambiente local | Docker + Docker Compose |
| Frontend | Blade, CSS e JavaScript puro |
| Testes | PHPUnit, SQLite em memória e HTTP simulado |
| Publicação | Laravel Cloud |

## 1. Preparar o ambiente local

### Pré-requisitos

- Git.
- Docker Engine com Compose v2, ou Docker Desktop **aberto e funcionando**.
- Internet para baixar imagens e dependências e consultar as integrações.
- Porta `8080` disponível.

Os comandos deste guia usam terminal **Linux, macOS ou Ubuntu/WSL2 no Windows**. No Windows, habilite a integração da sua distribuição em **Docker Desktop → Settings → Resources → WSL Integration**. Prefira clonar em `~/yourceat`, dentro do filesystem Linux.

Confira as ferramentas:

```bash
git --version
docker version
docker compose version
```

`docker version` precisa mostrar também o servidor, não apenas o cliente. Você **não precisa instalar PHP, Composer, MySQL ou Node na máquina**: PHP e Composer estão na imagem da aplicação; MySQL e Nginx rodam em containers.

### Clonar e criar a configuração privada

```bash
git clone https://github.com/NathanLuceat/yourceat.git
cd yourceat
cp .env.example .env
```

Se já existe um `.env`, **não repita a cópia por cima dele**. Os próximos comandos devem ser executados na pasta do projeto.

Abra `.env` no editor e ajuste as entradas abaixo. **Edite as linhas existentes, em vez de acrescentar versões duplicadas.** O exemplo atual repete `CACHE_STORE`; mantenha apenas uma entrada com o valor indicado.

```dotenv
APP_NAME=Yourceat
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=yourceat
DB_USERNAME=yourceat
DB_PASSWORD=secret

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

GITHUB_USERNAME=NathanLuceat
GITHUB_TOKEN=
GITHUB_CACHE_TTL=300
GITHUB_WEBHOOK_SECRET=
GITHUB_WEBHOOK_REPOSITORIES=

WEBBLIOTECA_URL=https://webblioteca.laravel.cloud
WEBBLIOTECA_TOKEN=
```

**Como interpretar esses valores:**

- `db` é o nome do serviço MySQL no Compose. Não troque por `localhost`: dentro do container, `localhost` é o próprio container da aplicação.
- Banco, usuário e senha acima correspondem ao `docker-compose.yml`. `secret` é uma senha de desenvolvimento, **não uma sugestão para produção**. Alterar só o `.env` não altera a criação do banco configurada no Compose.
- `APP_KEY` será gerada no próximo passo. Não copie a chave do site publicado.
- `GITHUB_USERNAME=NathanLuceat` mostra meus repositórios. Você pode usar seu próprio usuário, mas os textos pessoais da página são editados separadamente nas views.
- Os tokens vazios são intencionais neste exemplo: preencha-os apenas no seu `.env` privado para usar as integrações. Não são credenciais de demonstração.
- Substitua o placeholder `http://host.docker.internal:PORTA` da configuração de exemplo por uma URL real, como a URL HTTPS acima. Não acrescente `/api/v1/activities` à variável.
- Os webhooks podem ficar vazios no desenvolvimento local. Eles não são necessários para abrir o site.

**Nunca coloque tokens ou o `.env` no Git, em screenshots, logs ou mensagens públicas.**

### Subir os containers e instalar as dependências

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
```

- `up` sobe `app` (PHP-FPM), `db` (MySQL) e `web` (Nginx).
- `composer install` usa as versões do `composer.lock`; não substitua por `composer update` para instalar o projeto.
- `key:generate` preenche a chave da sua instalação local. Não precisa rodar novamente a cada inicialização.

Não é necessário executar `npm install`, `npm run build`, `composer run setup`, `php artisan serve` ou um worker de filas para este portfólio.

### Preparar o banco

O MySQL pode levar alguns segundos para inicializar na primeira execução. Confira:

```bash
docker compose logs db
```

Espere a inicialização terminar e o servidor indicar que aceita conexões. Então execute:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:status
docker compose ps
```

As migrations criam as tabelas da aplicação, incluindo atividades, sessões, cache e locks. Não é necessário criar migrations novas para o cache, popular dados com seed ou cadastrar um usuário para visitar o portfólio.

Se `migrate` retornar `Connection refused`, confira os logs do MySQL e repita depois que ele estiver pronto. O `depends_on` do Compose ordena a inicialização, mas não garante que o banco já esteja aceitando conexões.

### Abrir no navegador

- **Portfólio local:** <http://localhost:8080>
- **Currículo local:** <http://localhost:8080/curriculo>
- **Health da aplicação:** <http://localhost:8080/up>

Sem credenciais das integrações, a página pode mostrar estados vazios ou de indisponibilidade. A Webblioteca sem token registra falha de configuração; isso não significa que o Docker falhou. Para ver dados reais, siga a próxima seção.

O endpoint `/up` verifica a inicialização do Laravel: um HTTP 200 **não comprova autenticação nem disponibilidade das APIs externas**.

## 2. Conectar as APIs

### GitHub

1. No GitHub, abra **Settings → Developer settings → Personal access tokens → Fine-grained tokens**.
2. Crie um token com o mínimo de acesso necessário à leitura de dados públicos. O portfólio não precisa de permissões de escrita nem acesso a projetos privados.
3. Copie o valor diretamente para `GITHUB_TOKEN` no seu `.env`.
4. Confirme `GITHUB_USERNAME`.
5. Se a configuração estava cacheada, atualize-a localmente:

```bash
docker compose exec app php artisan config:clear
```

O token é usado pelo servidor e não é enviado ao navegador. Os limites da API variam conforme a autenticação; o cache evita repetir consultas a cada visita. Um token inválido pode resultar em 401, mesmo para dados públicos.

### Webblioteca

A integração precisa de um token Sanctum com habilidade `activities:read`, emitido **no mesmo ambiente/banco da Webblioteca indicado na URL**.

- Para consumir `https://webblioteca.laravel.cloud`, solicite um token ao responsável por esse ambiente.
- Se você administra a Webblioteca, o comando de emissão documentado para ela é:

```bash
php artisan activities:issue-token yourceat --email=EMAIL_DE_UM_USUARIO_EXISTENTE
```

**Esse comando pertence à Webblioteca, não ao Yourceat.** Execute-o somente no ambiente da Webblioteca que será consumido. Um token criado numa instalação local não autentica automaticamente na instalação publicada.

Salve o resultado em `WEBBLIOTECA_TOKEN`, confira a URL base e execute no Yourceat:

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan webblioteca:sync
```

O segundo comando faz uma chamada real à API e grava os eventos no banco local.

| Saída | Significado |
|---|---|
| `Webblioteca: synced (10)` | Um lote de dez eventos foi sincronizado. |
| `Webblioteca: synced (0)` | A consulta funcionou, sem eventos novos. |
| `Webblioteca: skipped (0)` | Execução dispensada por cooldown ou lock de outra execução. |
| `Webblioteca: failed (0)` | Falha de configuração, API, payload ou armazenamento; confira os logs. |

Sucesso e `skipped` retornam código de saída zero; `failed` retorna código 1. Se acabou de visitar a home, aguarde o cooldown antes de repetir o comando.

Para consumir uma Webblioteca rodando na sua máquina, use uma URL alcançável pelo container, por exemplo `http://host.docker.internal:8000` **somente se ela realmente estiver acessível nessa porta**. O Compose já configura o alias `host.docker.internal`.

## 3. Entender o fluxo, com exemplos

### GitHub: consulta, cache e documentação

O Laravel consulta o perfil e os repositórios do usuário configurado. Para os commits, consulta até quatro repositórios recentes, com até cinco commits por repositório, filtra pelo autor e mostra os **nove mais recentes entre os recebidos**. Se houver menos, não cria cards artificiais para completar a grade.

| Informação | Endpoint externo do GitHub |
|---|---|
| Perfil | `GET /users/{user}` |
| Repositórios | `GET /users/{user}/repos` |
| Commits | `GET /repos/{user}/{repo}/commits?author={user}` |
| README do perfil | `GET /repos/{user}/{user}/readme` |
| README de um projeto | `GET /repos/{user}/{repo}/readme` |

- Cache fresco de 300 segundos por padrão e cópia de fallback por até 24 horas na mesma geração.
- Repositórios privados, forks, arquivados e o repositório de perfil não entram na lista de projetos.
- Ao expandir um projeto, o navegador pede sua documentação ao Yourceat; a home não busca todos os READMEs de uma vez.
- Markdown é sanitizado. Links e imagens relativos dos projetos são resolvidos para o GitHub; conteúdo acima de 512 KiB oferece leitura integral no GitHub.
- README ausente é cacheado por 60 segundos. Ao renovar a documentação, a falha na confirmação de visibilidade pública impede o fallback desse caminho.

Exemplo local, usando `GITHUB_USERNAME=NathanLuceat`:

```bash
curl -H 'Accept: application/json' \
  'http://localhost:8080/projetos/yourceat/readme'
```

Resposta **ilustrativa**, não uma captura da produção:

```json
{
  "status": "available",
  "html": "<h1>Yourceat</h1><p>Documentação sanitizada.</p>",
  "url": "https://github.com/NathanLuceat/yourceat#readme"
}
```

Um projeto não elegível retorna 404; indisponibilidade temporária pode retornar 503. O endpoint público tem limite de 30 requisições por minuto.

### Webblioteca: API externa → banco local → navegador

1. A home, o polling ou o comando de sincronização chama o serviço.
2. O serviço usa o maior `external_id` salvo como cursor e pede até 50 eventos novos.
3. O lote é validado e gravado em transação, sem duplicar `external_id`.
4. O navegador recebe uma versão de apresentação dos dados locais, sem o token nem a identidade do ator.

A requisição externa é feita **pelo servidor**, não pelo JavaScript:

```http
GET https://webblioteca.laravel.cloud/api/v1/activities?since_id=0&limit=50
Accept: application/json
Authorization: Bearer <TOKEN_PRIVADO_DA_WEBBLIOTECA>
```

Resposta externa **fictícia para explicar o contrato**:

```json
{
  "data": [
    {
      "id": 1,
      "type": "emprestimo.criado",
      "summary": {"livro": "Livro de exemplo"},
      "actor": {"id": 42},
      "created_at": "2026-10-12T12:00:00Z"
    }
  ]
}
```

A identidade do ator não é persistida; o texto usa “Um usuário” ou “Sistema”. O resumo do evento é armazenado, por isso a origem também deve evitar dados pessoais nesse campo.

Você pode consultar o endpoint **local do Yourceat**, sem informar tokens:

```bash
curl -H 'Accept: application/json' \
  'http://localhost:8080/atividades?since_id=0'
```

Resposta local **ilustrativa** para o evento acima:

```json
{
  "items": [
    {
      "id": "1",
      "text": "Um usuário emprestou “Livro de exemplo”",
      "at": "2026-10-12T12:00:00+00:00"
    }
  ],
  "next_since_id": "1"
}
```

Na próxima consulta, use `since_id=1`. Sem novidades, `items` vem vazio e o cursor permanece igual. Os IDs são strings para não perder precisão no JavaScript.

**São três limites diferentes:** até 50 eventos importados por sincronização, até 20 retornados por consulta incremental e seis cards visíveis. O endpoint seleciona os próximos IDs antes de inverter a ordem para exibição, sem pular eventos intermediários.

Há cooldown de 20 segundos e lock contra sincronizações simultâneas. O polling ocorre a cada 30 segundos, pula abas ocultas e não sobrepõe requisições. “Lista consultada” confirma a consulta ao Yourceat, não o sucesso da API externa. Se a Webblioteca falhar, os dados já recebidos continuam no banco local.

## 4. Rotina de desenvolvimento

### Iniciar e parar

Para voltar a trabalhar depois da instalação:

```bash
docker compose up -d
```

Para parar os containers sem apagar os dados:

```bash
docker compose down
```

**Não acrescente `-v` ao comando de parada:** isso remove os volumes e pode apagar o banco local. Não use `migrate:fresh` como solução de rotina para erros.

### Sincronizar sem depender de visitas

O Compose não inicia o scheduler automaticamente. Se quiser exercitar a agenda local, abra outro terminal na pasta do projeto e execute:

```bash
docker compose exec app php artisan schedule:work
```

Deixe-o aberto e encerre com `Ctrl+C`. A tarefa é horária, não executa a cada minuto. Para conferir a agenda sem disparar sincronização:

```bash
docker compose exec app php artisan schedule:list
```

A expressão esperada é `0 * * * *` para `webblioteca:sync`. O fuso configurado no backend é UTC em `config/app.php`; o projeto não lê `APP_TIMEZONE` atualmente.

### Testar

No ambiente local, remova eventual configuração cacheada antes dos testes:

```bash
docker compose exec app php artisan config:clear
docker compose exec -T \
  -e APP_ENV=testing \
  -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=:memory: \
  -e DB_URL= \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  app php artisan test
```

A suíte usa SQLite em memória e chamadas HTTP simuladas. Não exige tokens funcionais, não consome as APIs reais e não deve usar o banco MySQL de desenvolvimento. Testes aprovados não comprovam disponibilidade dos serviços externos.

Para executar apenas os testes da view:

```bash
docker compose exec -T app php artisan test --filter PortfolioViewTest
```

O workflow `.github/workflows/tests.yml` executa a suíte em push e pull request, com PHP 8.4, dependências do lock e uma chave temporária. Consulte a aba **Actions** para ver cada execução. O workflow não faz deploy nem configura proteção de branch.

## 5. Configuração em produção

A instalação local é independente do site em **https://yourceat.laravel.cloud**. Não copie credenciais de produção para o tutorial nem use as senhas de desenvolvimento do Compose no Cloud.

No ambiente publicado, use `APP_URL=https://yourceat.laravel.cloud`, `APP_ENV=production` e `APP_DEBUG=false`. Banco e credenciais são os do próprio ambiente. Preserve a `APP_KEY` existente.

### Webhooks do GitHub

O receptor publicado é:

```text
https://yourceat.laravel.cloud/webhooks/github
```

No ambiente privado do Yourceat, configure `GITHUB_WEBHOOK_SECRET` com um valor aleatório exclusivo, diferente de `GITHUB_TOKEN`, e a lista autorizada:

```dotenv
GITHUB_USERNAME=NathanLuceat
GITHUB_WEBHOOK_REPOSITORIES=NathanLuceat/hidrauboa-site,NathanLuceat/webblioteca,NathanLuceat/yourceat,NathanLuceat/NathanLuceat
```

Em cada repositório da lista, abra **Settings → Webhooks → Add webhook** e use:

| Campo | Valor |
|---|---|
| Payload URL | `https://yourceat.laravel.cloud/webhooks/github` |
| Content type | `application/json` |
| Secret | O mesmo valor privado configurado no Cloud |
| SSL verification | Habilitada |
| Eventos | **Pushes** e **Repositories** |
| Active | Marcado |

Confira **Recent Deliveries**: `ping` deve responder 200 com `{"status":"pong"}`; um push autorizado, 200 com `{"status":"invalidated"}`. Não aponte os webhooks de produção para `localhost`.

O endpoint autentica o corpo bruto com HMAC SHA-256, valida proprietário e lista autorizada, limita o corpo a 1 MiB e aplica limite de 60 requisições por minuto por IP. Só essa rota POST substitui CSRF pela assinatura. Sem secret, responde 503; assinatura inválida, 401; repositório não autorizado, 403.

O webhook **não faz deploy nem busca a API durante a entrega**: invalida o cache do proprietário e a próxima consulta busca dados novos. O fallback anterior também é descartado, evitando reapresentá-lo depois de uma mudança de visibilidade. Isso pode deixar dados temporariamente indisponíveis se o GitHub falhar logo após o evento.

Entregas repetidas podem invalidar novamente. Após renomear um repositório, atualize a lista autorizada. Confira e reenvie entregas com falha pelo painel; uma resposta já enviada ao visitante não pode ser recolhida.

### Agenda horária no Laravel Cloud

1. Confirme as variáveis privadas da Webblioteca e o banco persistente com as migrations existentes aplicadas.
2. Use cache compartilhado entre HTTP, scheduler e réplicas. `CACHE_STORE=database` funciona com as tabelas `cache` e `cache_locks`; não use `array` ou cache local de uma instância em produção.
3. No painel de infraestrutura do ambiente Yourceat, abra **App cluster**, habilite **Scheduler**, salve e faça redeploy.
4. No console do ambiente, confira `php artisan schedule:list`.
5. Teste `php artisan webblioteca:sync`, sabendo que faz chamada real e grava os eventos.
6. Confirme uma execução automática no horário seguinte, sem disparar o comando manualmente.

O Cloud invoca `schedule:run` a cada minuto, mas a nossa tarefa fica devida apenas no minuto zero de cada hora. `onOneServer` e `withoutOverlapping` coordenam as execuções; não é necessário outro cron nem worker para essa agenda.

Sem visitantes, o limite é de até 50 eventos importados por hora; um backlog maior continua nas próximas execuções. Tarefas podem despertar Scale-to-Zero e gerar custo. Mudanças de agenda exigem novo deploy.

Referências: [Scheduler no Laravel Cloud](https://laravel.com/cloud/docs/scheduled-tasks.md) e [validação de webhooks no GitHub](https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries).

## 6. Problemas comuns

| Sintoma | O que conferir |
|---|---|
| Docker não encontrado no WSL | Docker Desktop aberto e integração da distribuição habilitada. |
| Não conecta ao daemon Docker | Inicie o Docker Engine/Desktop e tente `docker version` novamente. |
| Porta 8080 ocupada | Pare o outro serviço ou altere a porta publicada de `web` no Compose e ajuste `APP_URL`. |
| `vendor/autoload.php` não encontrado | Execute `docker compose exec app composer install`. |
| `No application encryption key` | Na primeira instalação local, execute `docker compose exec app php artisan key:generate`. |
| MySQL `Connection refused` | Aguarde a inicialização, confira `docker compose logs db` e use `DB_HOST=db`. |
| Tabela de cache, sessão ou atividades ausente | Execute as migrations existentes; não precisa gerar tabelas novas manualmente. |
| `Access denied` no MySQL | Confira credenciais do Compose e do `.env`. Mudar variáveis não altera usuários já criados no volume. Não apague o volume para contornar o erro. |
| Dados do GitHub antigos | Confira TTL e entregas dos webhooks; a cópia fresca padrão dura cinco minutos. |
| Webblioteca responde 401 | Confira token, ambiente de emissão, revogação, habilidade e configuração cacheada. Não desative Sanctum. |
| Página abre, mas feed está vazio | Configure a Webblioteca, execute `webblioteca:sync` e confira se a origem tem eventos. |
| Variável alterada não faz efeito | Localmente, execute `php artisan config:clear` dentro do container; no Cloud, aplique as alterações pelo deploy do ambiente. |

Para investigar logs locais:

```bash
docker compose logs --tail=100 app web db
docker compose exec app tail -n 100 storage/logs/laravel.log
```

O log Laravel pode ainda não existir antes da primeira escrita. Falhas da Webblioteca registram `Webblioteca sync failed` com etapa e status HTTP, sem corpo da resposta nem credenciais. Revise e remova informações sensíveis antes de compartilhar logs.

Se houver **Permission denied** ao gravar em `storage` ou `bootstrap/cache`, confira a propriedade dessas pastas. No ambiente Docker deste projeto, o PHP-FPM usa `www-data`. Uma correção restrita a elas é:

```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

Execute somente se esse for o problema: em Linux/WSL, o bind mount também altera a propriedade dessas pastas no host. Não aplique `chmod -R 777` nem permissões recursivas no projeto inteiro.

## 7. Onde personalizar

| Arquivo | Responsabilidade |
|---|---|
| `resources/views/home.blade.php` | Textos, serviços, contatos e estrutura do portfólio |
| `resources/views/curriculo.blade.php` | Currículo e link de retorno ao site |
| `resources/views/partials/project.blade.php` | Apresentação dos cases e módulos de documentação |
| `public/css/portfolio.css` | Paleta, tipografia, responsividade e impressão do currículo |
| `public/js/activities.js` | Polling e limite visual de seis atividades |
| `public/js/projects.js` | Carregamento sob demanda dos READMEs |
| `app/Services/GitHubService.php` | Consultas GitHub, cache e limite de commits |
| `app/Support/GitHubCache.php` | Gerações de cache por proprietário |
| `app/Services/WebbliotecaService.php` | Validação e sincronização dos eventos |
| `app/Console/Commands/SyncWebblioteca.php` | Comando de sincronização |
| `routes/console.php` | Agenda horária |
| `routes/web.php` | Páginas, endpoints JSON e webhook |
| `.github/workflows/tests.yml` | Testes no GitHub Actions |

As fontes Fraunces e Figtree vêm do Bunny Fonts; há fontes locais de fallback. Avatar e imagens da documentação também podem vir de provedores externos. As credenciais das APIs permanecem no servidor.

O `.gitignore` mantém credenciais, dependências, caches, logs e arquivos locais de editores/agentes fora do versionamento. Arquivos ocultos necessários, como `.env.example`, `.editorconfig` e `.gitignore`, continuam versionados. Ignorar um arquivo não apaga versões antigas: se uma credencial for exposta, revogue-a.

---

**Seu programador. Por Nathan Luceat.** [Conheça o portfólio](https://yourceat.laravel.cloud).
