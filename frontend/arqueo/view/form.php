<?php $cuenta = $cuentaArqueo ?? 'G'; ?>
<h1>Arqueo <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></h1>
<p id="saldos" class="muted"></p>
<label id="wrap-fisica" style="display:none">Caja física
    <select id="sel-fisica"></select>
</label>
<form id="form-arq">
    <label>Fecha <input type="date" name="fecha" required></label>
    <h2>Billetes</h2>
    <div class="arqueo-grid" id="billetes"></div>
    <h2>Monedas</h2>
    <div class="arqueo-grid" id="monedas"></div>
    <h2>Vales / cheques (importe)</h2>
    <div class="arqueo-grid">
        <label>Vale 1 <input name="vale0"></label>
        <label>Vale 2 <input name="vale1"></label>
        <label>Cheque 1 <input name="cheque0"></label>
        <label>Cheque 2 <input name="cheque1"></label>
    </div>
    <p id="totales"></p>
    <button type="submit">Guardar arqueo</button>
</form>
<script>
const CUENTA = <?= json_encode($cuenta) ?>;
const BILS = [500,200,100,50,20,10,5];
const MON = [2,1,0.5,0.2,0.1,0.05,0.02,0.01];
let fisicaId = null;
function grid(el, valores, prefix) {
  valores.forEach((v,i) => {
    const lab = document.createElement('label');
    lab.innerHTML = `${v} € <input type="number" min="0" step="1" name="${prefix}${i}" value="">`;
    el.appendChild(lab);
  });
}
async function refreshSaldos() {
  const url = fisicaId ? '/api/arqueos/fisica/' + fisicaId : '/api/arqueos/' + CUENTA;
  const r = await api(url);
  document.getElementById('saldos').textContent =
    'Saldo físico (P+G) ' + (r.saldo_fisico_es || r.saldo_fisico) +
    ' · P: ' + r.saldo_caja_p + ' · G: ' + r.saldo_caja_g;
}
document.addEventListener('DOMContentLoaded', async () => {
  grid(document.getElementById('billetes'), BILS, 'b');
  grid(document.getElementById('monedas'), MON, 'm');
  const cfg = await api('/api/configuracion');
  document.querySelector('[name=fecha]').value = cfg.config.fecha_cierre;
  const r = await api('/api/arqueos/' + CUENTA);
  fisicaId = r.cuenta_fisica_id;
  const cajas = r.cajas_activas || [];
  if (cajas.length > 1) {
    const wrap = document.getElementById('wrap-fisica');
    wrap.style.display = '';
    const sel = document.getElementById('sel-fisica');
    sel.innerHTML = cajas.map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
    sel.value = fisicaId;
    sel.onchange = () => { fisicaId = parseInt(sel.value, 10); refreshSaldos(); };
  }
  await refreshSaldos();
  document.getElementById('form-arq').onsubmit = async (ev) => {
    ev.preventDefault();
    const fd = formObj(ev.target);
    const desglose = {
      billetes: BILS.map((_,i) => fd['b'+i] || 0),
      monedas: MON.map((_,i) => fd['m'+i] || 0),
      vales: [fd.vale0, fd.vale1].filter(Boolean),
      cheques: [fd.cheque0, fd.cheque1].filter(Boolean),
    };
    const body = {fecha: fd.fecha, desglose};
    if (fisicaId) body.cuenta_fisica_id = fisicaId;
    const s = await api('/api/arqueos/' + CUENTA, {method:'POST', body});
    if (!s.ok) return alert(s.error);
    document.getElementById('totales').textContent =
      'Total ' + s.arqueo.total_es + ' (dinero ' + s.arqueo.total_dinero + ' + vales ' + s.arqueo.total_vales + ')';
  };
});
</script>
