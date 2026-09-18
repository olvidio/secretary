<h1><?= _("Apuntes de cierre de mes") ?></h1>
<p><?= _("Reparte lo que falta por cubrir de los gastos G 201–215 del mes. Del total se resta lo ya pagado en G/11 ese mes (incluido quien no entra en el reparto y el cierre automático ya generado). Lo pendiente se reparte entre quienes aportan a generales. Quien en el ejercicio ya lleva cubierta su cuota acumulada no recibe cierre este mes; el hueco lo cubren quienes van más atrasados. El retraso de meses anteriores no se deshace. Si «A generar» es cero, el mes ya está cubierto; «Generar apuntes» borra los automáticos de este mes y los recrea. Observaciones: «automático».") ?></p>
<p id="resumen" class="muted"></p>
<div id="aviso-faltantes" class="aviso-cierre" hidden>
  <p><strong><?= _("Faltan apuntes de vivienda en meses anteriores:") ?></strong> <span id="lista-faltantes"></span>.</p>
  <button type="button" id="btn-regularizar"><?= _("Regularizar meses anteriores") ?></button>
</div>
<p id="msg-faltantes" class="ok" hidden></p>
<table id="prev">
    <thead><tr><th><?= _("Iniciales") ?></th><th><?= _("Nombre") ?></th><th class="num"><?= _("Cuota") ?></th><th class="num"><?= _("Ya imputado (ejercicio)") ?></th><th class="num"><?= _("A generar") ?></th></tr></thead>
    <tbody></tbody>
</table>
<button type="button" id="btn-gen"><?= _("Generar apuntes") ?></button>
<p id="msg" class="ok" hidden></p>
<script>
const I18N_CIERRE = {
  error: <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>,
  confirmRegularizar: <?= json_encode(_("¿Generar los apuntes de vivienda de %s?"), JSON_UNESCAPED_UNICODE) ?>,
  regularizados: <?= json_encode(_("Regularizados %s apuntes: %s."), JSON_UNESCAPED_UNICODE) ?>,
  confirmGenerar: <?= json_encode(_("¿Borrar el cierre de este mes y generar de nuevo?"), JSON_UNESCAPED_UNICODE) ?>,
  creados: <?= json_encode(_("Creados %s apuntes"), JSON_UNESCAPED_UNICODE) ?>,
  nadaImputar: <?= json_encode(_("No falta nada por imputar este mes (gastos ya cubiertos). «Generar apuntes» sustituye cualquier cierre automático previo."), JSON_UNESCAPED_UNICODE) ?>,
};
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/cierre');
  if (!r.ok) return alert(r.error || I18N_CIERRE.error);
  let resumen = 'Tipo ' + r.tipo_cierre + ' · gastos G 201–215 ' + r.gastos_generales_es + ' € · mes ' + r.mes;
  if (r.aportaciones_externas_es && r.aportaciones_externas_es !== '0,00') {
    resumen += ' · ya aportado fuera del reparto ' + r.aportaciones_externas_es + ' €';
  }
  resumen += ' · pendiente ' + (r.restante_es ?? r.base_reparto_es ?? r.gastos_generales_es) + ' €';
  document.getElementById('resumen').textContent = resumen;
  const faltantes = (r.meses_faltantes && r.meses_faltantes.meses) || [];
  if (faltantes.length) {
    document.getElementById('aviso-faltantes').hidden = false;
    document.getElementById('lista-faltantes').textContent = faltantes
      .map(m => m.mes_es + ' (' + m.gastos_es + ' €)')
      .join(', ');
  }
  const tb = document.querySelector('#prev tbody');
  let pendiente = false;
  (r.lineas || []).forEach(l => {
    if (l.importe !== '0.00' && l.importe !== '0') pendiente = true;
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(l.iniciales)}</td><td>${esc(l.nombre)}</td>`
      + `<td class="num">${esc(l.importe_bruto_es)}</td>`
      + `<td class="num">${esc(l.ya_imputado_es)}</td>`
      + `<td class="num">${esc(l.importe_es)}</td>`;
    tb.appendChild(tr);
  });
  if (!pendiente && (r.lineas || []).length) {
    document.getElementById('msg').hidden = false;
    document.getElementById('msg').textContent = I18N_CIERRE.nadaImputar;
  }
  document.getElementById('btn-regularizar').onclick = async () => {
    const lista = faltantes.map(m => m.mes_es).join(', ');
    if (!confirm(I18N_CIERRE.confirmRegularizar.replace('%s', lista))) return;
    const btn = document.getElementById('btn-regularizar');
    btn.disabled = true;
    const s = await api('/api/cierre/regularizar', { method: 'POST', body: {} });
    btn.disabled = false;
    const msg = document.getElementById('msg-faltantes');
    msg.hidden = false;
    if (!s.ok) {
      msg.textContent = s.error || I18N_CIERRE.error;
      return;
    }
    if (s.mensaje) {
      msg.textContent = s.mensaje;
      return;
    }
    const detalle = (s.meses || []).map(m => m.mes_es + ' (' + m.creados + ' apuntes)').join(', ');
    msg.textContent = I18N_CIERRE.regularizados
      .replace('%s', s.creados)
      .replace('%s', detalle);
    document.getElementById('aviso-faltantes').hidden = true;
  };
  document.getElementById('btn-gen').onclick = async () => {
    if (!confirm(I18N_CIERRE.confirmGenerar)) return;
    const s = await api('/api/cierre', {method:'POST', body:{}});
    document.getElementById('msg').hidden = false;
    document.getElementById('msg').textContent = s.ok
      ? I18N_CIERRE.creados.replace('%s', s.creados)
      : s.error;
  };
});
</script>
