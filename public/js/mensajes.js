document.addEventListener('DOMContentLoaded', async () => {
  const lista = document.getElementById('mensajes-lista');
  const vacio = document.getElementById('mensajes-vacio');
  const err = document.getElementById('mensajes-err');
  if (!lista) return;
  const r = await api('/api/mensajes');
  if (!r.ok) {
    if (err) {
      err.hidden = false;
      err.textContent = r.error || t('error');
    }
    return;
  }
  const items = r.mensajes || [];
  if (vacio) vacio.hidden = items.length > 0;
  const ids = [];
  items.forEach((m) => {
    ids.push(m.id);
    const li = document.createElement('li');
    li.className = m.leido ? 'leido' : 'nuevo';
    const fecha = fmtFecha(m.fecha || '');
    let accion = '';
    if (m.href && m.accion) {
      accion = '<p><a href="' + esc(m.href) + '" data-ir-yo="1">' + esc(m.accion) + '</a></p>';
    }
    li.innerHTML = '<p class="meta"><strong>' + esc(m.titulo || '') + '</strong>'
      + (fecha ? '<small>' + esc(fecha) + '</small>' : '') + '</p>'
      + '<p>' + esc(m.cuerpo || '') + '</p>'
      + accion
      + '<p><button type="button" class="del" data-descartar="' + esc(String(m.id)) + '">'
      + esc(typeof t === 'function' ? t('quitar') : 'Quitar') + '</button></p>';
    lista.appendChild(li);
  });
  lista.addEventListener('click', async (ev) => {
    const btnDesc = ev.target.closest('button[data-descartar]');
    if (btnDesc) {
      ev.preventDefault();
      const mid = parseInt(btnDesc.getAttribute('data-descartar') || '0', 10);
      if (!mid || !confirm(typeof t === 'function' ? t('mensajes_quitar_confirm') : '¿Quitar este aviso?')) {
        return;
      }
      const quitar = await api('/api/mensajes/' + mid + '/descartar', { method: 'POST', body: {} });
      if (!quitar.ok) {
        alert(quitar.error || (typeof t === 'function' ? t('error') : 'Error'));
        return;
      }
      btnDesc.closest('li')?.remove();
      if (lista.children.length === 0 && vacio) vacio.hidden = false;
      if (typeof pintarAvisosMensajes === 'function') pintarAvisosMensajes();
      return;
    }
    const a = ev.target.closest('a[data-ir-yo]');
    if (!a || document.body.classList.contains('yo')) return;
    ev.preventDefault();
    const href = a.getAttribute('href') || '/yo/remesas';
    const cambio = await api('/api/preferencias/tipo', { method: 'POST', body: { tipo: 'persona' } });
    if (!cambio.ok) {
      alert(cambio.error || t('error'));
      return;
    }
    location.href = cambio.siguiente === '/elegir-persona' ? '/elegir-persona' : href;
  });
  if (ids.length > 0) {
    const marca = await api('/api/mensajes/leidos', { method: 'POST', body: { ids } });
    if (marca.ok && typeof pintarAvisosMensajes === 'function') {
      pintarAvisosMensajes();
    }
  }
});
