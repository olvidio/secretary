<h1>Apuntes</h1>
<form id="filtros" class="filters">
    <select name="cuenta"><option value="">P y G</option><option>P</option><option>G</option></select>
    <select name="origen"><option value="">A/B/C</option><option>A</option><option>B</option><option>C</option></select>
    <input name="iniciales" placeholder="Iniciales">
    <input name="concepto" placeholder="Concepto">
    <input type="date" name="desde">
    <input type="date" name="hasta">
    <button type="submit">Filtrar</button>
</form>
<table id="tabla-apuntes">
    <thead>
    <tr><th>Fecha</th><th>P/G</th><th>A/B/C</th><th>Inic.</th><th>Concepto</th><th>Observaciones</th><th class="num">Cantidad</th><th></th></tr>
    </thead>
    <tbody></tbody>
</table>
<script>
async function loadApuntes() {
  const q = new URLSearchParams(formObj(document.getElementById('filtros')));
  const r = await api('/api/apuntes?' + q.toString());
  const tb = document.querySelector('#tabla-apuntes tbody');
  tb.innerHTML = '';
  (r.apuntes || []).forEach(a => {
    const tr = document.createElement('tr');
    if (a.es_cierre) tr.classList.add('cierre');
    const esRemesa = a.es_remesa || (a.observaciones && String(a.observaciones).indexOf('Remesa ') === 0);
    const borrar = esRemesa ? '' : '<button data-del="' + a.id + '">Borrar</button>';
    tr.innerHTML = `<td>${fechaCelda(a)}</td><td>${esc(a.cuenta)}</td><td>${esc(a.origen)}</td>
      <td>${esc(a.iniciales || '')}</td><td>${esc(a.concepto_codigo)}</td>
      <td>${esc(a.observaciones || '')}</td><td class="num">${esc(a.cantidad_es)}</td>
      <td>${borrar}</td>`;
    const btn = tr.querySelector('[data-del]');
    if (btn) {
      btn.onclick = async () => {
        if (!confirm('¿Borrar apunte?')) return;
        await api('/api/apuntes/' + a.id, {method:'DELETE'});
        loadApuntes();
      };
    }
    tb.appendChild(tr);
  });
}
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('filtros').onsubmit = (ev) => { ev.preventDefault(); loadApuntes(); };
  loadApuntes();
});
function fechaCelda(a) {
  if (a.fecha_imputacion_es) {
    return esc(a.fecha_es) + ' <span class="muted">(imp. ' + esc(a.fecha_imputacion_es) + ')</span>';
  }
  return esc(a.fecha_es);
}
</script>
