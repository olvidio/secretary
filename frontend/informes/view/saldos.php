<h1>Saldos</h1>
<form id="form-saldos" class="filters">
    <label>Hasta <input type="date" name="hasta"></label>
    <button type="submit">Calcular</button>
</form>
<p id="tot"></p>
<h2>Tesorería física</h2>
<table id="tabla-tesoreria">
    <thead>
    <tr><th>Cuenta</th><th class="num">P</th><th class="num">G</th><th class="num">Físico</th></tr>
    </thead>
    <tbody></tbody>
</table>
<table id="tabla-saldos">
    <thead><tr><th>Persona</th><th class="num">Saldo apuntes A</th></tr></thead>
    <tbody></tbody>
</table>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const cfg = await api('/api/configuracion');
  document.querySelector('[name=hasta]').value = cfg.config.fecha_cierre;
  async function load() {
    const hasta = document.querySelector('[name=hasta]').value;
    const r = await api('/api/informes/saldos?hasta=' + hasta);
    document.getElementById('tot').innerHTML =
      `Caja <strong>${esc(r.caja_es)}</strong> · Banco <strong>${esc(r.banco_es)}</strong> · Saldo A (filtro) ${esc(r.saldo_a_es)}`;
    const tb = document.querySelector('#tabla-saldos tbody');
    tb.innerHTML = '';
    (r.por_persona || []).forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${esc(p.nombre)} (${esc(p.iniciales)})</td><td class="num">${esc(p.saldo_a_es)}</td>`;
      tb.appendChild(tr);
    });
    const tr = await api('/api/informes/tesoreria?hasta=' + hasta);
    const tbt = document.querySelector('#tabla-tesoreria tbody');
    tbt.innerHTML = '';
    (tr.cuentas || []).forEach(c => {
      const row = document.createElement('tr');
      row.innerHTML = `<td>${esc(c.tipo)} · ${esc(c.nombre)}</td>
        <td class="num">${esc(c.saldo_p_es)}</td><td class="num">${esc(c.saldo_g_es)}</td>
        <td class="num">${esc(c.saldo_fisico_es)}</td>`;
      tbt.appendChild(row);
    });
  }
  document.getElementById('form-saldos').onsubmit = (ev) => { ev.preventDefault(); load(); };
  load();
});
</script>
