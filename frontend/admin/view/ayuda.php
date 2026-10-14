<h1><?= _("Ayuda — manual pendiente") ?></h1>
<p class="muted"><?= _("Preguntas que la ayuda con IA no pudo contestar porque no estaban en el manual. Se indica quién las hizo por si debe contestarle directamente. Expórtelas, amplíe docs/manual/ en local y vuelva a desplegar.") ?></p>

<p class="admin-ayuda-acciones">
    <button type="button" id="btn-export-md"><?= _("Descargar Markdown") ?></button>
    <button type="button" id="btn-export-json"><?= _("Descargar JSON") ?></button>
</p>

<p id="ayuda-pend-meta" class="muted"></p>

<table id="tabla-ayuda-pend" hidden>
    <thead>
        <tr>
            <th><?= _("Pregunta") ?></th>
            <th class="num"><?= _("Veces") ?></th>
            <th><?= _("Quién") ?></th>
            <th><?= _("Ámbitos") ?></th>
            <th><?= _("Última") ?></th>
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
  const r = await api('/api/admin/ayuda/pendientes');
  if (!r.ok) return alert(r.error || 'Error');
  const version = r.version_manual || '';
  const lista = r.preguntas || [];
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
    tr.innerHTML = '<td>' + esc(p.pregunta) + '</td><td class="num">' + esc(String(p.veces)) + '</td>'
      + '<td>' + esc(textoConsultantes(p.consultantes)) + '</td>'
      + '<td>' + esc(ambitos) + '</td><td>' + esc(p.ultima_en || '') + '</td>';
    tb.appendChild(tr);
  });
  document.getElementById('btn-export-md').onclick = () => {
    window.location.href = '/api/admin/ayuda/pendientes/export?formato=md';
  };
  document.getElementById('btn-export-json').onclick = () => {
    window.location.href = '/api/admin/ayuda/pendientes/export?formato=json';
  };
});
</script>
