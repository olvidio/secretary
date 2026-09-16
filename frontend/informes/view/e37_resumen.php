<h1>Resumen cuentas personales</h1>
<p class="print-hide"><button type="button" onclick="window.print()">Imprimir</button></p>
<table id="tabla-res">
    <thead>
    <tr>
        <th>Persona</th><th class="num">Ingresos</th><th class="num">Gastos</th>
        <th class="num">Disponible</th><th class="num">Ay. fam.</th>
        <th class="num">Lab. ap.</th><th class="num">Saldo F.</th><th class="num">Saldo c/c</th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<p id="aviso-saldo-cc" class="aviso-saldo-cc" hidden></p>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/informes/e37-resumen');
  const tb = document.querySelector('#tabla-res tbody');
  (r.filas || []).forEach(f => {
    const tr = document.createElement('tr');
    const ccNeg = String(f.saldo_cc_es || '').startsWith('-');
    tr.innerHTML = `<td>${esc(f.nombre)}</td>
      <td class="num">${esc(f.ingresos_es)}</td><td class="num">${esc(f.gastos_es)}</td>
      <td class="num">${esc(f.disponible_es)}</td><td class="num">${esc(f.ay_fam_es)}</td>
      <td class="num">${esc(f.lab_ap_es)}</td><td class="num">${esc(f.saldo_final_es)}</td>
      <td class="num${ccNeg ? ' saldo-cc-neg' : ''}">${esc(f.saldo_cc_es)}</td>`;
    tb.appendChild(tr);
  });
  const aviso = document.getElementById('aviso-saldo-cc');
  if (r.aviso_saldo_cc) {
    aviso.hidden = false;
    aviso.textContent = r.aviso_saldo_cc;
  }
});
</script>
