(() => {
  const queue = [];
  let active = 0;

  function drain() {
    while (active < 2 && queue.length) {
      active++;
      queue.shift()().finally(() => { active--; drain(); });
    }
  }

  document.querySelectorAll('.project-documentation').forEach(panel => {
    const status = panel.querySelector('.readme-status');
    const content = panel.querySelector('[data-project-readme]');
    const retry = panel.querySelector('.readme-retry');
    let loaded = false;
    let pending = false;

    function load() {
      if (loaded || pending) return;
      pending = true;
      retry.hidden = true;
      content.setAttribute('aria-busy', 'true');
      status.textContent = 'Carregando documentação do GitHub…';
      queue.push(async () => {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 20_000);
        try {
          const response = await fetch(panel.dataset.readmeUrl, {
            headers: { Accept: 'application/json' }, signal: controller.signal,
          });
          if (!response.ok) throw new Error('readme unavailable');
          const result = await response.json();
          if (result.status === 'available' && typeof result.html === 'string') {
            // Only sanitized Markdown from the same-origin Laravel endpoint.
            content.innerHTML = result.html;
            status.textContent = 'Documentação do repositório no GitHub.';
          } else if (result.status === 'missing') {
            status.textContent = 'Este repositório não tem um README disponível. Explore o código no GitHub.';
          } else if (result.status === 'too_large') {
            status.textContent = 'Este README é muito extenso para exibir aqui. Leia a versão completa no GitHub.';
          } else {
            throw new Error('readme unavailable');
          }
          loaded = true;
        } catch {
          status.textContent = 'Não foi possível carregar a documentação. Tente novamente ou leia no GitHub.';
          retry.hidden = false;
        } finally {
          clearTimeout(timeout);
          pending = false;
          content.removeAttribute('aria-busy');
        }
      });
      drain();
    }

    panel.addEventListener('toggle', () => { if (panel.open) load(); });
    retry.addEventListener('click', load);
  });

  document.querySelectorAll('.brand-photo img, .portrait-frame img').forEach(img => {
    const fallback = () => { img.hidden = true; };
    img.addEventListener('error', fallback);
    if (img.complete && !img.naturalWidth) fallback();
  });
})();
