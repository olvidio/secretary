<h1>Apuntes de cierre de mes</h1>
<p>Reparte los gastos G 201–215 del mes de la fecha de cierre entre quienes aportan vivienda a generales. Quien no aporta no entra. La exención de meses solo deja fuera a quien aporta y llega o se va a mitad de año. Observaciones: «automático». Se pueden borrar y volver a generar.</p>
<p id="resumen" class="muted"></p>
<table id="prev">
    <thead><tr><th>Iniciales</th><th>Nombre</th><th class="num">Importe</th></tr></thead>
    <tbody></tbody>
</table>
<button type="button" id="btn-gen">Generar apuntes</button>
<p id="msg" class="ok" hidden></p>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/cierre');
  document.getElementById('resumen').textContent =
    'Tipo ' + r.tipo_cierre + ' · gastos generales ' + r.gastos_generales_es + ' · mes ' + r.mes;
  const tb = document.querySelector('#prev tbody');
  (r.lineas || []).forEach(l => {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${esc(l.iniciales)}</td><td>${esc(l.nombre)}</td><td class="num">${esc(l.importe_es)}</td>`;
    tb.appendChild(tr);
  });
  document.getElementById('btn-gen').onclick = async () => {
    if (!confirm('¿Borrar el cierre de este mes y generar de nuevo?')) return;
    const s = await api('/api/cierre', {method:'POST', body:{}});
    document.getElementById('msg').hidden = false;
    document.getElementById('msg').textContent = s.ok ? ('Creados ' + s.creados + ' apuntes') : s.error;
  };
});
</script>
