<section id="solicitudes-vinculo" class="solicitudes-vinculo">
    <h2><?= _("Solicitudes de acceso personal") ?></h2>
    <p class="muted"><?= _("Peticiones de usuarios del libro personal para unirse a este centro. Puede dar de alta un nombre nuevo o vincular a uno existente.") ?></p>
    <div id="solicitudes-lista"></div>
    <p id="solicitudes-vacio" class="muted" hidden><?= _("No hay solicitudes pendientes.") ?></p>
</section>
<script>
const I18N_SOL_VINCULO = {
  error: <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>,
  nuevoNombre: <?= json_encode(_("Dar de alta y vincular"), JSON_UNESCAPED_UNICODE) ?>,
  vincularExistente: <?= json_encode(_("Vincular existente…"), JSON_UNESCAPED_UNICODE) ?>,
  rechazar: <?= json_encode(_("Rechazar"), JSON_UNESCAPED_UNICODE) ?>,
  confirmNuevo: <?= json_encode(_("¿Dar de alta y vincular la cuenta?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmRechazar: <?= json_encode(_("¿Rechazar la solicitud?"), JSON_UNESCAPED_UNICODE) ?>,
  cargando: <?= json_encode(_("Cargando nombres parecidos…"), JSON_UNESCAPED_UNICODE) ?>,
  sinParecidos: <?= json_encode(_("No hay nombres parecidos. Use «Dar de alta y vincular»."), JSON_UNESCAPED_UNICODE) ?>,
  yaVinculado: <?= json_encode(_(" (ya vinculado)"), JSON_UNESCAPED_UNICODE) ?>,
  otraCuenta: <?= json_encode(_(" (otra cuenta)"), JSON_UNESCAPED_UNICODE) ?>,
  vincular: <?= json_encode(_("Vincular"), JSON_UNESCAPED_UNICODE) ?>,
  confirmVincular: <?= json_encode(_("¿Vincular a %s?"), JSON_UNESCAPED_UNICODE) ?>,
  anio: <?= json_encode(_("año"), JSON_UNESCAPED_UNICODE) ?>,
};
async function cargarSolicitudesVinculo() {
  const wrap = document.getElementById('solicitudes-lista');
  const vacio = document.getElementById('solicitudes-vacio');
  if (!wrap) return;
  const r = await api('/api/vinculos-centro/solicitudes');
  if (!r.ok) {
    wrap.innerHTML = '<p class="error">' + esc(r.error || I18N_SOL_VINCULO.error) + '</p>';
    return;
  }
  const items = r.solicitudes || [];
  vacio.hidden = items.length > 0;
  wrap.innerHTML = '';
  for (const s of items) {
    const box = document.createElement('article');
    box.className = 'solicitud-vinculo-box';
    box.innerHTML =
      '<div class="solicitud-vinculo-cuerpo">'
      + '<div class="solicitud-vinculo-info">'
      + '<p><strong>' + esc(s.solicitante_nombre || s.solicitante_email) + '</strong>'
      + ' · ' + esc(s.solicitante_email || '')
      + ' · ' + esc(I18N_SOL_VINCULO.anio) + ' ' + esc(s.anio)
      + (s.mensaje ? '<br><span class="muted">' + esc(s.mensaje) + '</span>' : '')
      + '</p>'
      + '</div>'
      + '<div class="solicitud-vinculo-acciones">'
      + '<button type="button" data-nuevo>' + esc(I18N_SOL_VINCULO.nuevoNombre) + '</button>'
      + '<button type="button" data-vincular>' + esc(I18N_SOL_VINCULO.vincularExistente) + '</button>'
      + '<button type="button" data-rechazar class="peligro">' + esc(I18N_SOL_VINCULO.rechazar) + '</button>'
      + '</div>'
      + '</div>'
      + '<div class="solicitud-candidatos" hidden></div>';
    box.querySelector('[data-nuevo]').onclick = async () => {
      if (!confirm(I18N_SOL_VINCULO.confirmNuevo)) return;
      const casilla = document.getElementById('asumo-responsable-nombres');
      if (!casilla || !casilla.checked) {
        return alert(typeof I18N_PERSONAS !== 'undefined' ? I18N_PERSONAS.faltaResponsable : I18N_SOL_VINCULO.error);
      }
      const res = await api('/api/vinculos-centro/solicitudes/' + s.id + '/aprobar', {
        method: 'POST',
        body: { asumo_responsable_nombres: '1' },
      });
      if (!res.ok) return alert(res.error);
      await cargarSolicitudesVinculo();
      if (typeof loadPersonas === 'function') loadPersonas();
    };
    box.querySelector('[data-rechazar]').onclick = async () => {
      if (!confirm(I18N_SOL_VINCULO.confirmRechazar)) return;
      const res = await api('/api/vinculos-centro/solicitudes/' + s.id + '/rechazar', { method: 'POST', body: {} });
      if (!res.ok) return alert(res.error);
      await cargarSolicitudesVinculo();
    };
    const candWrap = box.querySelector('.solicitud-candidatos');
    box.querySelector('[data-vincular]').onclick = async () => {
      candWrap.hidden = false;
      candWrap.innerHTML = '<p class="muted">' + esc(I18N_SOL_VINCULO.cargando) + '</p>';
      const cr = await api('/api/vinculos-centro/solicitudes/' + s.id + '/candidatos');
      if (!cr.ok) {
        candWrap.innerHTML = '<p class="error">' + esc(cr.error) + '</p>';
        return;
      }
      const cands = cr.candidatos || [];
      if (!cands.length) {
        candWrap.innerHTML = '<p class="muted">' + esc(I18N_SOL_VINCULO.sinParecidos) + '</p>';
        return;
      }
      candWrap.innerHTML = '<ul class="lista-candidatos"></ul>';
      const ul = candWrap.querySelector('ul');
      cands.forEach((p) => {
        const li = document.createElement('li');
        const extra = p.tiene_cuenta
          ? (p.cuenta_es_solicitante ? I18N_SOL_VINCULO.yaVinculado : I18N_SOL_VINCULO.otraCuenta)
          : '';
        li.innerHTML = esc(p.iniciales + ' — ' + (p.nombre_completo || p.nombre) + extra);
        if (!p.tiene_cuenta || p.cuenta_es_solicitante) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.textContent = I18N_SOL_VINCULO.vincular;
          btn.onclick = async () => {
            if (!confirm(I18N_SOL_VINCULO.confirmVincular.replace('%s', p.iniciales))) return;
            const casilla = document.getElementById('asumo-responsable-nombres');
            if (!casilla || !casilla.checked) {
              return alert(typeof I18N_PERSONAS !== 'undefined' ? I18N_PERSONAS.faltaResponsable : I18N_SOL_VINCULO.error);
            }
            const res = await api('/api/vinculos-centro/solicitudes/' + s.id + '/aprobar', {
              method: 'POST',
              body: { persona_id: p.id, asumo_responsable_nombres: '1' },
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
