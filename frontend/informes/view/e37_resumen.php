<h1 class="print-hide"><?= _("Resumen cuentas personales") ?></h1>
<p class="print-hide"><button type="button" onclick="window.print()"><?= _("Imprimir") ?></button></p>
<p class="informe-print-cab"><?= _("Resumen cuentas personales") ?></p>
<table id="tabla-res" class="informe-print-tabla">
    <thead>
    <tr>
        <th><?= _("Persona") ?></th><th class="num"><?= _("Ingresos") ?></th><th class="num"><?= _("Gastos") ?></th>
        <th class="num"><?= _("Disponible") ?></th><th class="num"><?= _("Ay. fam.") ?></th>
        <th class="num"><?= _("Lab. ap.") ?></th><th class="num"><?= _("Saldo F.") ?></th>        <th class="num"><?= _("Saldo cont.") ?></th>
        <th class="num"><?= _("Deuda viv.") ?></th>
        <th class="num"><?= _("Sup. viv.") ?></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<p id="aviso-saldo-cc" class="aviso-saldo-cc" hidden></p>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  document.body.classList.add('informe-resumen-hoja');
  const r = await api('/api/informes/e37-resumen');
  const tb = document.querySelector('#tabla-res tbody');
  (r.filas || []).forEach(f => {
    const tr = document.createElement('tr');
    const ccNeg = String(f.saldo_cc_es || '').startsWith('-');
    const sup = f.superavit_viv_es || '0,00';
    const supCls = (sup !== '0,00' && sup !== '-0,00') ? ' superavit-viv' : '';
    tr.innerHTML = `<td>${esc(f.nombre)}</td>
      <td class="num">${esc(f.ingresos_es)}</td><td class="num">${esc(f.gastos_es)}</td>
      <td class="num">${esc(f.disponible_es)}</td><td class="num">${esc(f.ay_fam_es)}</td>
      <td class="num">${esc(f.lab_ap_es)}</td><td class="num">${esc(f.saldo_final_es)}</td>
      <td class="num${ccNeg ? ' saldo-cc-neg' : ''}">${esc(f.saldo_cc_es)}</td>
      <td class="num">${esc(f.deuda_viv_es || '0,00')}</td>
      <td class="num${supCls}">${esc(sup)}</td>`;
    tb.appendChild(tr);
  });
  const aviso = document.getElementById('aviso-saldo-cc');
  if (r.aviso_saldo_cc || r.aviso_deuda || r.aviso_superavit) {
    aviso.hidden = false;
    aviso.textContent = [r.aviso_saldo_cc, r.aviso_deuda, r.aviso_superavit].filter(Boolean).join(' ');
  }
});
</script>
