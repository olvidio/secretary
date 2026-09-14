<section id="solicitudes-vinculo" class="solicitudes-vinculo">
    <h2>Solicitudes de acceso personal</h2>
    <p class="muted">Peticiones de usuarios del libro personal para unirse a este centro. Puede dar de alta un nombre nuevo o vincular a uno existente.</p>
    <div id="solicitudes-lista"></div>
    <p id="solicitudes-vacio" class="muted" hidden>No hay solicitudes pendientes.</p>
</section>
<script>
async function cargarSolicitudesVinculo() {
  const wrap = document.getElementById('solicitudes-lista');
  const vacio = document.getElementById('solicitudes-vacio');
  if (!wrap) return;
  const r = await api('/api/vinculos-centro/solicitudes');
  if (!r.ok) {
    wrap.innerHTML = '<p class="error">' + esc(r.error || 'Error') + '</p>';
    return;
  }
  const items = r.solicitudes || [];
  vacio.hidden = items.length > 0;
  wrap.innerHTML = '';
  for (const s of items) {
    const box = document.createElement('article');
    box.className = 'solicitud-vinculo-box';
    box.innerHTML =
      '<p><strong>' + esc(s.solicitante_nombre || s.solicitante_email) + '</strong>'
      + ' · ' + esc(s.solicitante_email || '')
      + ' · año ' + esc(s.anio)
      + (s.mensaje ? '<br><span class="muted">' + esc(s.mensaje) + '</span>' : '')
      + '</p>'
      + '<div class="solicitud-vinculo-acciones">'
      + '<button type="button" data-nuevo>Nuevo nombre</button> '
      + '<button type="button" data-vincular>Vincular existente…</button> '
      + '<button type="button" data-rechazar class="peligro">Rechazar</button>'
      + '</div>'
      + '<div class="solicitud-candidatos" hidden></div>';
    box.querySelector('[data-nuevo]').onclick = async () => {
      if (!confirm('¿Crear un nombre nuevo y vincular la cuenta?')) return;
      const res = await api('/api/vinculos-centro/solicitudes/' + s.id + '/aprobar', { method: 'POST', body: {} });
      if (!res.ok) return alert(res.error);
      await cargarSolicitudesVinculo();
      if (typeof loadPersonas === 'function') loadPersonas();
    };
    box.querySelector('[data-rechazar]').onclick = async () => {
      if (!confirm('¿Rechazar la solicitud?')) return;
      const res = await api('/api/vinculos-centro/solicitudes/' + s.id + '/rechazar', { method: 'POST', body: {} });
      if (!res.ok) return alert(res.error);
      await cargarSolicitudesVinculo();
    };
    const candWrap = box.querySelector('.solicitud-candidatos');
    box.querySelector('[data-vincular]').onclick = async () => {
      candWrap.hidden = false;
      candWrap.innerHTML = '<p class="muted">Cargando nombres parecidos…</p>';
      const cr = await api('/api/vinculos-centro/solicitudes/' + s.id + '/candidatos');
      if (!cr.ok) {
        candWrap.innerHTML = '<p class="error">' + esc(cr.error) + '</p>';
        return;
      }
      const cands = cr.candidatos || [];
      if (!cands.length) {
        candWrap.innerHTML = '<p class="muted">No hay nombres parecidos. Use «Nuevo nombre».</p>';
        return;
      }
      candWrap.innerHTML = '<ul class="lista-candidatos"></ul>';
      const ul = candWrap.querySelector('ul');
      cands.forEach((p) => {
        const li = document.createElement('li');
        const extra = p.tiene_cuenta
          ? (p.cuenta_es_solicitante ? ' (ya vinculado)' : ' (otra cuenta)')
          : '';
        li.innerHTML = esc(p.iniciales + ' — ' + (p.nombre_completo || p.nombre) + extra);
        if (!p.tiene_cuenta || p.cuenta_es_solicitante) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.textContent = 'Vincular';
          btn.onclick = async () => {
            if (!confirm('¿Vincular a ' + p.iniciales + '?')) return;
            const res = await api('/api/vinculos-centro/solicitudes/' + s.id + '/aprobar', {
              method: 'POST',
              body: { persona_id: p.id },
            });
            if (!res.ok) return alert(res.error);
            await cargarSolicitudesVinculo();
            if (typeof loadPersonas === 'function') loadPersonas();
          };
          li.appendChild(document.createTextNode(' '));
          li.appendChild(btn);
        }
        ul.appendChild(li);
      });
    };
    wrap.appendChild(box);
  }
}
document.addEventListener('DOMContentLoaded', () => { cargarSolicitudesVinculo(); });
</script>
