<?php $cuenta = $cuentaEntrada ?? 'P'; ?>
<h1>Conceptos <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></h1>
<table id="tabla-conceptos">
    <thead><tr><th>Código</th><th>Nombre</th><th>Descripción</th><th>Naturaleza</th></tr></thead>
    <tbody></tbody>
</table>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/conceptos?cuenta=' + <?= json_encode($cuenta) ?>);
  const tb = document.querySelector('#tabla-conceptos tbody');
  (r.conceptos || []).forEach(c => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(c.codigo)}</td><td>${esc(c.nombre)}</td><td>${esc(c.descripcion)}</td><td>${esc(c.naturaleza)}</td>`;
    tb.appendChild(tr);
  });
});
</script>
