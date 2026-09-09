<?php $cuenta = $cuentaInforme ?? 'P'; ?>
<h1>Resumen mensual 613 <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></h1>
<p id="cab" class="muted"></p>
<p class="print-hide">
    <a href="/arqueo-<?= strtolower($cuenta) ?>">Arqueo <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></a>
    · <button type="button" onclick="window.print()">Imprimir</button>
</p>
<table id="tabla-613">
    <thead><tr><th>Partida</th><th class="num">Previsto</th><th class="num">Realizado</th><th class="num">%</th></tr></thead>
    <tbody></tbody>
</table>
<section id="extra"></section>
<form id="obs-form" class="print-hide">
    <label>Observaciones
        <textarea name="observaciones" rows="3"></textarea>
    </label>
    <?php if ($cuenta === 'P'): ?>
        <label>Saldo c/c personales (manual) <input name="saldo_cc_personales"></label>
    <?php else: ?>
        <label>Media cocina (mes) <input name="media_cocina_mes"></label>
        <label>Media cocina (acumulada) <input name="media_cocina_acum"></label>
    <?php endif; ?>
    <button type="submit">Guardar observaciones</button>
</form>
<script>
const CUENTA = <?= json_encode($cuenta) ?>;
function pct(v) {
  if (v === null || v === undefined) return '';
  return (v * 100).toFixed(1) + ' %';
}
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/informes/613/' + CUENTA);
  document.getElementById('cab').textContent =
    r.config.centro + ' · cierre ' + fmtFecha(r.config.fecha_cierre) + ' · ' + r.config.meses + ' meses';
  const tb = document.querySelector('#tabla-613 tbody');
  const tot = r.totales || {};
  const bloques = CUENTA === 'P'
    ? [
        ['I. Ingresos', tot.ingresos],
        ...r.lineas.filter(l => ['111','112','113','12'].includes(l.codigo)).map(l => [l.etiqueta, l]),
        ['II. Gastos personales', tot.gastos],
        ...r.lineas.filter(l => ['21','22','23','24','25','26','27','28'].includes(l.codigo)).map(l => [l.etiqueta, l]),
        ['III. Disponible', tot.disponible],
        ['IV. Ayudas familiares', r.lineas.find(l => l.codigo==='4')],
        ['V. Atención labores', tot.atencion_labores],
        ...r.lineas.filter(l => ['51','52','6'].includes(l.codigo)).map(l => [l.etiqueta, l]),
        ['VII. Otras labores', tot.labores],
        ...r.lineas.filter(l => ['71','72','73','74','75','76','77','78','79'].includes(l.codigo)).map(l => [l.etiqueta, l]),
        ['VIII. Saldo final', tot.saldo_final],
        ['IX. Saldo c/c', r.lineas.find(l => l.codigo==='9')],
      ]
    : [
        ['I. Ingresos', tot.ingresos],
        ...r.lineas.filter(l => ['11','12','13','14','15'].includes(l.codigo)).map(l => [l.etiqueta, l]),
        ['II. Gastos', tot.gastos],
        ...r.lineas.filter(l => String(l.codigo).startsWith('20')).map(l => [l.etiqueta, l]),
        ['Saldo ingresos-gastos', tot.saldo_ingresos_gastos],
        ['Disponible al inicio', r.lineas.find(l => l.codigo==='32')],
        ['III. Disponible', tot.disponible],
      ];
  bloques.forEach(([et, l]) => {
    if (!l) return;
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(et)}</td><td class="num">${esc(l.previsto_es)}</td>
      <td class="num">${esc(l.realizado_es)}</td><td class="num">${pct(l.pct)}</td>`;
    tb.appendChild(tr);
  });
  if (CUENTA === 'G') {
    document.getElementById('extra').innerHTML =
      `<p>Personas: ${r.num_personas} · Gasto vivienda/persona/mes: ${esc(r.gasto_vivienda_persona_mes_es)}</p>
       <p>Saldo caja: <strong>${esc(r.saldo_caja_es)}</strong> · Saldo banco: <strong>${esc(r.saldo_banco_es)}</strong></p>`;
  }
  const f = document.getElementById('obs-form');
  if (f.observaciones) f.observaciones.value = r.observaciones || '';
  if (f.saldo_cc_personales) f.saldo_cc_personales.value = r.saldo_cc_personales || '';
  if (f.media_cocina_mes) f.media_cocina_mes.value = r.media_cocina_mes || '';
  if (f.media_cocina_acum) f.media_cocina_acum.value = r.media_cocina_acum || '';
  f.onsubmit = async (ev) => {
    ev.preventDefault();
    const body = formObj(ev.target);
    if (CUENTA === 'P') body.observaciones_613_p = body.observaciones;
    else body.observaciones_613_g = body.observaciones;
    const s = await api('/api/configuracion', {method:'POST', body});
    if (!s.ok) alert(s.error);
  };
});
</script>
