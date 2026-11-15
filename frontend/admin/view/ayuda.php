<h1><?= _("Ayuda — manual pendiente") ?></h1>
<p class="muted"><?= _("Preguntas por repasar: las que la ayuda con IA no pudo contestar porque no estaban en el manual y las que contestó pero alguien marcó que no le había servido. Se indica quién las hizo por si debe contestarle directamente. Expórtelas, amplíe docs/manual/ en local, vuelva a desplegar y ciérrelas aquí como cubiertas o descartadas.") ?></p>

<p class="admin-ayuda-acciones">
    <button type="button" id="btn-export-md"><?= _("Descargar Markdown") ?></button>
    <button type="button" id="btn-export-json"><?= _("Descargar JSON") ?></button>
    <button type="button" id="btn-cubrir-todas"><?= _("Marcar todas como cubiertas") ?></button>
</p>

<p id="ayuda-pend-meta" class="muted"></p>

<table id="tabla-ayuda-pend" hidden>
    <thead>
        <tr>
            <th><?= _("Pregunta") ?></th>
            <th><?= _("Motivo") ?></th>
            <th class="num"><?= _("Veces") ?></th>
            <th><?= _("Quién") ?></th>
            <th><?= _("Ámbitos") ?></th>
            <th><?= _("Última") ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody></tbody>
</table>
<p id="ayuda-pend-vacio" class="muted" hidden><?= _("No hay preguntas pendientes registradas.") ?></p>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  const meta = document.getElementById('ayuda-pend-meta');
  const tb = document.querySelector('#tabla-ayuda-pend tbody');
  const tabla = document.getElementById('tabla-ayuda-pend');
  const vacio = document.getElementById('ayuda-pend-vacio');
  const T = {
    sinRespuesta: <?= json_encode(_("Sin respuesta"), JSON_UNESCAPED_UNICODE) ?>,
    noSirvio: <?= json_encode(_("No sirvió"), JSON_UNESCAPED_UNICODE) ?>,
    respuesta: <?= json_encode(_("Respuesta:"), JSON_UNESCAPED_UNICODE) ?>,
    cubierta: <?= json_encode(_("Cubierta"), JSON_UNESCAPED_UNICODE) ?>,
    descartada: <?= json_encode(_("Descartada"), JSON_UNESCAPED_UNICODE) ?>,
    confirmarTodas: <?= json_encode(_("¿Marcar como cubiertas todas las preguntas de la lista? Dejarán de salir aquí y en la exportación."), JSON_UNESCAPED_UNICODE) ?>,
  };
  const r = await api('/api/admin/ayuda/pendientes');
  if (!r.ok) return alert(r.error || 'Error');
  pintar(r);
  async function repasar(body) {
    const s = await api('/api/admin/ayuda/pendientes/repasar', { method: 'POST', body });
    if (!s.ok) return alert(s.error || 'Error');
    pintar(s);
  }
  tb.addEventListener('click', (ev) => {
    const btn = ev.target.closest('button[data-estado]');
    if (btn) repasar({ pregunta: btn.closest('tr').dataset.pregunta, estado: btn.dataset.estado });
  });
  document.getElementById('btn-cubrir-todas').onclick = () => {
    if (confirm(T.confirmarTodas)) repasar({ todas: true, estado: 'cubierta' });
  };
  function pintar(r) {
  const version = r.version_manual || '';
  const lista = r.preguntas || [];
  document.getElementById('btn-cubrir-todas').hidden = lista.length === 0;
  meta.textContent = <?= json_encode(_("Versión del manual:"), JSON_UNESCAPED_UNICODE) ?> + ' ' + version
    + ' · ' + lista.length + ' ' + <?= json_encode(_("preguntas distintas"), JSON_UNESCAPED_UNICODE) ?>;
  vacio.hidden = lista.length > 0;
  tabla.hidden = lista.length === 0;
  tb.replaceChildren();
  function textoConsultantes(consultantes) {
    const listaC = consultantes || [];
    if (!listaC.length) return '—';
    return listaC.map((c) => {
      let t = c.etiqueta || c.email || c.alias || ('#' + c.identidad_id);
      if (c.veces > 1) t += ' (' + c.veces + '×)';
      return t;
    }).join('; ');
  }
  lista.forEach((p) => {
    const tr = document.createElement('tr');
    const ambitos = (p.ambitos || []).join(', ') || '—';
    tr.dataset.pregunta = p.pregunta;
    const noSirvio = p.motivo === 'no_sirvio';
    let detalle = '';
    if (noSirvio) {
      detalle = '<details class="admin-ayuda-detalle"><summary>' + esc(T.respuesta) + '</summary>'
        + '<p>' + esc(p.ultima_respuesta || '').replace(/\n/g, '<br>') + '</p></details>'
        + (p.comentarios || []).filter((c) => c.texto).map((c) =>
          '<p class="admin-ayuda-comentario">«' + esc(c.texto) + '» — ' + esc(c.quien) + '</p>').join('');
    }
    tr.innerHTML = '<td>' + esc(p.pregunta) + detalle + '</td>'
      + '<td>' + esc(noSirvio ? T.noSirvio + ' (' + p.votos_negativos + ')' : T.sinRespuesta) + '</td>'
      + '<td class="num">' + esc(String(p.veces)) + '</td>'
      + '<td>' + esc(textoConsultantes(p.consultantes)) + '</td>'
      + '<td>' + esc(ambitos) + '</td><td>' + esc(p.ultima_en || '') + '</td>'
      + '<td class="admin-ayuda-cerrar"><button type="button" data-estado="cubierta">' + esc(T.cubierta) + '</button> '
      + '<button type="button" data-estado="descartada">' + esc(T.descartada) + '</button></td>';
    tb.appendChild(tr);
  });
  }
  document.getElementById('btn-export-md').onclick = () => {
    window.location.href = '/api/admin/ayuda/pendientes/export?formato=md';
  };
  document.getElementById('btn-export-json').onclick = () => {
    window.location.href = '/api/admin/ayuda/pendientes/export?formato=json';
  };
});
</script>
