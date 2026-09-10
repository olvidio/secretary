<?php $cuenta = $cuentaArqueo ?? 'G'; ?>
<p class="print-hide arqueo-volver" id="arqueo-volver" hidden>
    <a href="/613-<?= strtolower($cuenta) ?>">← Volver al resumen 613 <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></a>
</p>
<h1>Arqueo <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></h1>
<p id="saldos" class="muted"></p>
<label id="wrap-fisica" style="display:none">Caja física
    <select id="sel-fisica"></select>
</label>
<form id="form-arq">
    <label class="arqueo-fecha">Fecha <input type="date" name="fecha" required></label>
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
    <p class="arqueo-total" id="totales">
        <strong>Total:</strong> <span id="total-valor">0,00</span> €
        <span id="total-dif" class="arqueo-dif" aria-live="polite">Diferencia: 0,00 €</span>
        <button type="button" id="btn-capuchinos" class="arqueo-capuchinos-btn" hidden>Buscar capuchinos</button>
        <span class="arqueo-total-detalle" id="total-detalle"></span>
    </p>
    <button type="submit">Guardar arqueo</button>
</form>
<div id="capuchinos-res" class="arqueo-capuchinos" hidden></div>
<script>
const CUENTA = <?= json_encode($cuenta) ?>;
const BILS = [500,200,100,50,20,10,5];
const MON = [2,1,0.5,0.2,0.1,0.05,0.02,0.01];
let fisicaId = null;
let saldoContable = 0;

function fmtEuro(n) {
  return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function cents(n) {
  return Math.round(n * 100);
}

function parseImporte(raw) {
  if (raw === null || raw === undefined || raw === '') return 0;
  const s = String(raw).trim();
  if (!s) return 0;
  const n = s.includes(',')
    ? Number(s.replace(/\./g, '').replace(',', '.'))
    : Number(s.replace(',', '.'));
  return Number.isFinite(n) ? n : 0;
}

function calcTotal(form) {
  const fd = formObj(form);
  let dinero = 0;
  BILS.forEach((v, i) => { dinero += (parseFloat(fd['b' + i]) || 0) * v; });
  MON.forEach((v, i) => { dinero += (parseFloat(fd['m' + i]) || 0) * v; });
  let vales = 0;
  ['vale0', 'vale1', 'cheque0', 'cheque1'].forEach((k) => { vales += parseImporte(fd[k]); });
  return { dinero, vales, total: dinero + vales };
}

function mostrarTotal(form) {
  const { dinero, vales, total } = calcTotal(form);
  document.getElementById('total-valor').textContent = fmtEuro(total);
  const det = document.getElementById('total-detalle');
  if (dinero || vales) {
    det.textContent = `(dinero ${fmtEuro(dinero)} + vales ${fmtEuro(vales)})`;
  } else {
    det.textContent = '';
  }
  const dif = total - saldoContable;
  const elDif = document.getElementById('total-dif');
  elDif.textContent = 'Diferencia: ' + fmtEuro(dif) + ' €';
  const ok = cents(dif) === 0;
  elDif.classList.toggle('error', !ok);
  elDif.classList.toggle('ok', ok);
  document.getElementById('totales').classList.toggle('alerta', !ok);
  const btn = document.getElementById('btn-capuchinos');
  const cap = Math.abs(cents(dif));
  btn.hidden = !(cap > 0 && cap % 9 === 0);
  document.getElementById('capuchinos-res').hidden = true;
}

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
    'Saldo contable (P+G) ' + (r.saldo_fisico_es || r.saldo_fisico) +
    ' · P: ' + r.saldo_caja_p + ' · G: ' + r.saldo_caja_g;
  saldoContable = parseImporte(r.saldo_fisico);
  const form = document.getElementById('form-arq');
  if (form) mostrarTotal(form);
}

function pintarCapuchinos(s) {
  const box = document.getElementById('capuchinos-res');
  box.hidden = false;
  if (!s.aplicable) {
    box.innerHTML = '<p class="muted">La diferencia no es múltiplo de 9.</p>';
    return;
  }
  const apuntes = s.apuntes || [];
  let html = '<h2>Capuchinos (cifras invertidas)</h2>'
    + '<p class="muted">Si se escribieron dos cifras al revés (o se corrió la coma), '
    + 'la diferencia es múltiplo de 9. Apuntes de caja cuyo importe, cambiado así, '
    + 'explicaría el descuadre de ' + esc(s.diferencia_es || s.diferencia) + ' €.</p>';
  if (!apuntes.length) {
    html += '<p>Ningún apunte de caja encaja.</p>';
    box.innerHTML = html;
    return;
  }
  html += '<table><thead><tr>'
    + '<th>Fecha</th><th>P/G</th><th>Inic.</th><th>Concepto</th>'
    + '<th>Observaciones</th><th class="num">Anotado</th><th class="num">Si fuera</th>'
    + '<th></th></tr></thead><tbody>';
  apuntes.forEach((a) => {
    const alts = (a.alternativas || []).map((x) => x.cantidad_es || x.cantidad).join(', ');
    const from = location.pathname + location.search;
    const href = '/apuntes?origen=C&cuenta=' + encodeURIComponent(a.cuenta || '')
      + '&desde=' + encodeURIComponent(a.fecha || '')
      + '&hasta=' + encodeURIComponent(a.fecha || '')
      + '&from=' + encodeURIComponent(from);
    html += '<tr>'
      + '<td>' + esc(a.fecha_es || a.fecha) + '</td>'
      + '<td>' + esc(a.cuenta) + '</td>'
      + '<td>' + esc(a.iniciales) + '</td>'
      + '<td>' + esc(a.concepto_codigo) + '</td>'
      + '<td>' + esc(a.observaciones) + '</td>'
      + '<td class="num">' + esc(a.cantidad_es || a.cantidad) + '</td>'
      + '<td class="num">' + esc(alts) + '</td>'
      + '<td><a href="' + href + '">Ver</a></td>'
      + '</tr>';
  });
  html += '</tbody></table>';
  box.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', async () => {
  if (new URLSearchParams(location.search).get('from') === '613') {
    document.getElementById('arqueo-volver').hidden = false;
  }

  grid(document.getElementById('billetes'), BILS, 'b');
  grid(document.getElementById('monedas'), MON, 'm');
  const form = document.getElementById('form-arq');
  form.addEventListener('input', () => mostrarTotal(form));

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
  mostrarTotal(form);

  document.getElementById('btn-capuchinos').onclick = async () => {
    const { total } = calcTotal(form);
    const difCents = Math.abs(cents(total - saldoContable));
    if (difCents === 0 || difCents % 9 !== 0) return;
    const fecha = form.querySelector('[name=fecha]')?.value || '';
    const q = new URLSearchParams({
      diferencia: (difCents / 100).toFixed(2),
    });
    if (fecha) q.set('hasta', fecha);
    const s = await api('/api/arqueos/capuchinos?' + q.toString());
    if (!s.ok) return alert(s.error);
    pintarCapuchinos(s);
  };

  form.onsubmit = async (ev) => {
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
    mostrarTotal(form);
  };
});
</script>
