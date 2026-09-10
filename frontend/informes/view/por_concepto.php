<h1>Apuntes de un concepto</h1>
<form id="form-conc" class="filters">
    <select name="cuenta"><option>P</option><option>G</option></select>
    <select name="concepto"></select>
    <button type="submit">Ver</button>
</form>
<table>
    <thead><tr><th>Fecha</th><th>P/G</th><th>A/B/C</th><th>Inic.</th><th>Observaciones</th><th class="num">Cantidad</th><th></th></tr></thead>
    <tbody id="tb"></tbody>
</table>
<p id="suma"></p>
<script>
async function loadConceptos() {
  const cuenta = document.querySelector('[name=cuenta]').value;
  const r = await api('/api/conceptos?cuenta=' + cuenta);
  const sel = document.querySelector('[name=concepto]');
  sel.innerHTML = '';
  (r.conceptos || []).forEach(c => {
    const o = document.createElement('option');
    o.value = c.codigo;
    o.textContent = c.etiqueta;
    sel.appendChild(o);
  });
}
document.addEventListener('DOMContentLoaded', async () => {
  await loadConceptos();
  document.querySelector('[name=cuenta]').onchange = loadConceptos;
  document.getElementById('form-conc').onsubmit = async (ev) => {
    ev.preventDefault();
    const cuenta = ev.target.cuenta.value;
    const concepto = ev.target.concepto.value;
    const r = await api('/api/apuntes?cuenta=' + cuenta + '&concepto=' + encodeURIComponent(concepto));
    const tb = document.getElementById('tb');
    tb.innerHTML = '';
    let s = 0;
    (r.apuntes || []).forEach(a => {
      s += parseFloat(a.cantidad);
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${esc(a.fecha_es)}</td><td>${esc(a.cuenta)}</td><td>${esc(a.origen)}</td>
        <td>${esc(a.iniciales || '')}</td><td>${esc(a.observaciones || '')}</td>
        <td class="num">${esc(a.cantidad_es)}</td>
        <td class="col-acc">${accionesApunteHtml(a)}</td>`;
      enlazarAccionesApunte(tr, a, () => document.getElementById('form-conc').requestSubmit());
      tb.appendChild(tr);
    });
    document.getElementById('suma').textContent = 'Suma: ' + s.toFixed(2);
  };
});
</script>
