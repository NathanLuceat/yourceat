(() => {
  const section = document.getElementById('webblioteca');
  if (!section) return;

  const list = document.getElementById('webblioteca-list');
  const status = document.getElementById('webblioteca-status');
  if (!list || !status) return;
  let polling = false;

  function trimActivities() {
    [...list.querySelectorAll('li[data-id]')].slice(6).forEach(item => item.remove());
  }
  trimActivities();

  // último id que a página já mostra (vem do servidor, no atributo data-next-since-id)
  let sinceId = section.dataset.nextSinceId ? String(section.dataset.nextSinceId) : '0';

  async function poll() {
    if (document.hidden || polling) return; // aba em segundo plano: não gasta requisição
    polling = true;

    try {
      const res = await fetch(`/atividades?since_id=${encodeURIComponent(sinceId)}`, {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) throw new Error(res.status);

      const { items, next_since_id } = await res.json();

      // next_since_id vem como string (pra não perder precisão em ids grandes)

      if (items.length) document.getElementById('webblioteca-empty')?.remove();

      // a API manda do mais novo para o mais antigo; inserimos do mais antigo
      // para o mais novo, assim o mais recente termina no topo
      for (const a of [...items].reverse()) {
        if (BigInt(a.id) <= BigInt(sinceId) || list.querySelector(`[data-id="${a.id}"]`)) continue; // já recebido, mesmo fora da tela

        const li = document.createElement('li');
        li.className = 'activity-card';
        li.dataset.id = String(a.id);

        const mark = document.createElement('span');
        mark.className = 'activity-mark';
        mark.setAttribute('aria-hidden', 'true');
        mark.textContent = 'W';

        const text = document.createElement('p');
        text.className = 'activity-text';
        text.textContent = a.text; // textContent evita injeção de HTML

        const time = document.createElement('time');
        time.className = 'activity-time';
        time.dateTime = a.at;
        time.textContent = new Date(a.at).toLocaleString('pt-BR', {
          day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
        });

        li.append(mark, text, time);
        list.prepend(li);
      }

      trimActivities();
      if (next_since_id !== null && next_since_id !== undefined && /^\d+$/.test(String(next_since_id)) && BigInt(next_since_id) > BigInt(sinceId)) {
        sinceId = String(next_since_id);
      }
      status.textContent = `Lista consultada às ${new Date().toLocaleTimeString('pt-BR')}`;
    } catch {
      status.textContent = 'Não foi possível consultar a lista. Nova tentativa em 30 segundos.';
    } finally {
      polling = false;
    }
  }

  poll();                       // consulta logo ao abrir a página
  setInterval(poll, 30_000);    // e depois a cada 30 segundos
})();