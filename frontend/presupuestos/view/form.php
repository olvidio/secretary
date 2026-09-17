<?php $cuenta = $cuentaPresupuesto ?? 'P'; ?>
<h1><?= sprintf(_("Presupuesto %s"), htmlspecialchars($cuenta, ENT_QUOTES)) ?></h1>
<p class="muted"><?= _("Celdas de previsto anual. El 613 prorratea × meses / 12.") ?></p>
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

document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/presupuestos/' + CUENTA);
  if (!r.ok) return alert(r.error || <?= json_encode(_("No se pudo cargar el presupuesto"), JSON_UNESCAPED_UNICODE) ?>);
  pintarLineasPresu(r.lineas);
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
