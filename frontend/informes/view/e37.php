<h1>Cuentas personales (E37)</h1>
<form id="sel" class="filters">
    <select name="iniciales"><option value="">Todas</option></select>
    <button type="submit">Ver</button>
</form>
<table id="tabla-e37">
    <thead>
    <tr><th>Fecha</th><th>Inic.</th><th>Concepto</th><th>Observaciones</th><th class="num">Cantidad</th><th></th></tr>
    </thead>
    <tbody></tbody>
</table>
<div id="tot"></div>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const pers = await api('/api/personas');
  const sel = document.querySelector('[name=iniciales]');
  (pers.personas || []).forEach(p => {
    const o = document.createElement('option');
    o.value = p.iniciales;
    o.textContent = p.nombre_completo + ' (' + p.iniciales + ')';
    sel.appendChild(o);
  });
  async function load() {
    const ini = sel.value;
    const r = await api('/api/informes/e37' + (ini ? '?iniciales=' + encodeURIComponent(ini) : ''));
    const tb = document.querySelector('#tabla-e37 tbody');
    tb.innerHTML = '';
    (r.apuntes || []).forEach(a => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${esc(a.fecha_es)}</td><td>${esc(a.iniciales || '')}</td>
        <td>${esc(a.concepto_codigo)}</td><td>${esc(a.observaciones || '')}</td>
        <td class="num">${esc(a.cantidad_es)}</td>
        <td class="col-acc">${accionesApunteHtml(a)}</td>`;
      enlazarAccionesApunte(tr, a, load);
      tb.appendChild(tr);
    });
    const t = r.totales;
    document.getElementById('tot').innerHTML = t
      ? `<p>Ingresos ${esc(t.ingresos_es)} · Gastos ${esc(t.gastos_es)} · Disponible ${esc(t.disponible_es)} · Saldo final ${esc(t.saldo_final_es)}</p>`
      : '';
  }
  document.getElementById('sel').onsubmit = (ev) => { ev.preventDefault(); load(); };
  load();
});
</script>
