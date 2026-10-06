<?php $cuenta = $cuentaPresupuesto ?? 'P'; $esCentroSg = !empty($esCentroSg); ?>
<h1><?= sprintf(_("Presupuesto %s"), htmlspecialchars($cuenta, ENT_QUOTES)) ?></h1>
<p class="muted"><?= _("Celdas de previsto anual. El 613 prorratea × meses / 12.") ?></p>
<?php if ($esCentroSg && $cuenta === 'G'): ?>
<p class="muted"><?= _("Los destinos del 42 al 54 se nombran en") ?>
    <a href="/conceptos-g"><?= _("Conceptos") ?></a><?= _(", en Plan y ejercicio.") ?></p>
<?php endif; ?>
<?php if ($cuenta === 'P'): ?>
<p class="muted"><?= _("Para generar las cifras del libro P, use") ?>
    <a href="/prevision-personal"><?= _("Previsión personal") ?></a>
    <?= _("y") ?>
    <a href="/prevision"><?= _("Previsión") ?></a>.</p>
<?php endif; ?>
<form id="form-presu">
<table>
    <thead><tr><th><?= _("Concepto") ?></th><th class="num"><?= _("Previsto") ?></th></tr></thead>
    <tbody></tbody>
</table>
<button type="submit"><?= _("Guardar") ?></button>
<p id="msg" class="ok" hidden><?= _("Guardado") ?></p>
</form>
<script>
const CUENTA = <?= json_encode($cuenta) ?>;

function pintarLineasPresu(lineas) {
  const tb = document.querySelector('#form-presu tbody');
  tb.innerHTML = '';
  (lineas || []).forEach((l) => {
    const tr = document.createElement('tr');
    const inp = document.createElement('input');
    inp.name = l.concepto_codigo;
    inp.value = fmtImporteEs(l.previsto_es ?? l.previsto);
    inp.className = 'num';
    inp.addEventListener('blur', () => {
      if (inp.value.trim()) inp.value = fmtImporteEs(inp.value);
    });
    tr.innerHTML = `<td>${esc(l.concepto_codigo)} ${esc(l.nombre || '')}</td>`;
    const td = document.createElement('td');
    td.className = 'num';
    td.appendChild(inp);
    tr.appendChild(td);
    tb.appendChild(tr);
  });
}

async function cargarPresupuesto() {
  const r = await api('/api/presupuestos/' + CUENTA);
  if (!r.ok) return alert(r.error || <?= json_encode(_("No se pudo cargar el presupuesto"), JSON_UNESCAPED_UNICODE) ?>);
  pintarLineasPresu(r.lineas);
}

document.addEventListener('DOMContentLoaded', async () => {
  await cargarPresupuesto();
  document.getElementById('form-presu').onsubmit = async (ev) => {
    ev.preventDefault();
    const lineas = {};
    ev.target.querySelectorAll('input[name]').forEach((i) => {
      lineas[i.name] = i.value.trim() ? fmtImporteEs(i.value) : '';
    });
    const s = await api('/api/presupuestos/' + CUENTA, { method: 'POST', body: { lineas } });
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) return alert(s.error);
    pintarLineasPresu(s.lineas);
  };
});
</script>
