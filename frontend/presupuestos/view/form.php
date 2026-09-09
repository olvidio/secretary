<?php $cuenta = $cuentaPresupuesto ?? 'P'; ?>
<h1>Presupuesto <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></h1>
<p class="muted">Celdas de previsto anual. El 613 prorratea × meses / 12.</p>
<form id="form-presu">
<table>
    <thead><tr><th>Concepto</th><th class="num">Previsto</th></tr></thead>
    <tbody></tbody>
</table>
<button type="submit">Guardar</button>
<p id="msg" class="ok" hidden>Guardado</p>
</form>
<script>
const CUENTA = <?= json_encode($cuenta) ?>;
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/presupuestos/' + CUENTA);
  const tb = document.querySelector('#form-presu tbody');
  (r.lineas || []).forEach(l => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(l.concepto_codigo)} ${esc(l.nombre || '')}</td>
      <td class="num"><input name="${esc(l.concepto_codigo)}" value="${esc(l.previsto)}"></td>`;
    tb.appendChild(tr);
  });
  document.getElementById('form-presu').onsubmit = async (ev) => {
    ev.preventDefault();
    const lineas = {};
    ev.target.querySelectorAll('input[name]').forEach(i => { lineas[i.name] = i.value; });
    const s = await api('/api/presupuestos/' + CUENTA, {method:'POST', body:{lineas}});
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) alert(s.error);
  };
});
</script>
