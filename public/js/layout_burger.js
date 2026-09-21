(function () {
  const cfg = window.secretarioMenus || { nav: '', grupoActivo: '', grupos: [] };
  const itemsUl = document.getElementById('burgerItems');
  const sidebar = document.getElementById('burgerSidebar');
  const overlay = document.getElementById('burgerOverlay');
  const toggle = document.getElementById('burgerToggle');
  let grupoMostrado = cfg.grupoActivo || '';

  function vaciarPagina() {
    const main = document.querySelector('body.layout-burger main');
    if (!main) return;
    main.replaceChildren();
  }

  function pintarGrupo(groupId) {
    const grupo = (cfg.grupos || []).find((g) => g.id === groupId);
    if (!grupo || !itemsUl) return;
    if (window.innerWidth <= 1024) {
      cerrarSidebar();
    }
    if (groupId === grupoMostrado) return;
    if (groupId === cfg.grupoActivo) {
      location.reload();
      return;
    }
    const items = grupo.items || [];
    if (items.length === 1) {
      window.location.href = items[0].href;
      return;
    }
    document.querySelectorAll('#burgerGroups [data-group]').forEach((a) => {
      a.classList.toggle('active', a.getAttribute('data-group') === groupId);
    });
    itemsUl.innerHTML = '';
    items.forEach((item) => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = item.href;
      a.textContent = item.label;
      li.appendChild(a);
      itemsUl.appendChild(li);
    });
    vaciarPagina();
    grupoMostrado = groupId;
  }

  function abrirSidebar() {
    sidebar?.classList.add('open');
    if (overlay) overlay.hidden = false;
  }

  function cerrarSidebar() {
    sidebar?.classList.remove('open');
    if (overlay) overlay.hidden = true;
  }

  document.querySelectorAll('#burgerGroups [data-group]').forEach((a) => {
    a.addEventListener('click', (ev) => {
      ev.preventDefault();
      pintarGrupo(a.getAttribute('data-group') || '');
    });
  });

  toggle?.addEventListener('click', () => {
    if (sidebar?.classList.contains('open')) {
      cerrarSidebar();
    } else {
      abrirSidebar();
    }
  });
  overlay?.addEventListener('click', cerrarSidebar);
})();
