<?php
$tipoTesoreria = ($arqueoTesoreria ?? 'caja') === 'banco' ? 'banco' : 'caja';
$esBanco = $tipoTesoreria === 'banco';
$tituloArqueo = $esBanco ? _('Saldo banco') : _('Arqueo Caja');
$etiquetaFisica = $esBanco ? _('Cuenta bancaria') : _('Caja física');
?>
<p class="print-hide arqueo-volver" id="arqueo-volver" hidden>
    <a href="/613-p" id="arqueo-volver-link"><?= _("← Volver al resumen 613") ?></a>
</p>
<h1><?= htmlspecialchars($tituloArqueo, ENT_QUOTES) ?></h1>
<?php if ($esBanco): ?>
<p class="muted print-hide"><?= _("Indique el saldo según el extracto bancario y compárelo con el saldo contable (libro personal + general en esa cuenta).") ?></p>
<?php else: ?>
<p class="muted print-hide"><?= _("El saldo contable es la suma del libro personal y del general en esta tesorería; el dinero físico es uno solo.") ?></p>
<?php endif; ?>
<p id="saldos" class="muted"></p>
<p id="sin-fisica" class="error print-hide" hidden></p>
<label id="wrap-fisica" style="display:none"><?= htmlspecialchars($etiquetaFisica, ENT_QUOTES) ?>
    <select id="sel-fisica"></select>
</label>
<form id="form-arq">
    <label class="arqueo-fecha"><?= _("Fecha") ?> <input type="date" name="fecha" required></label>
    <?php if ($esBanco): ?>
    <h2><?= _("Saldo según extracto") ?></h2>
    <p class="arqueo-grid">
        <label><?= _("Importe (€)") ?> <input name="saldo_extracto" inputmode="decimal" autocomplete="off" required></label>
    </p>
    <?php else: ?>
    <h2><?= _("Billetes") ?></h2>
    <div class="arqueo-grid" id="billetes"></div>
    <h2><?= _("Monedas") ?></h2>
    <div class="arqueo-grid" id="monedas"></div>
    <h2><?= _("Vales / cheques (importe)") ?></h2>
    <div class="arqueo-grid">
        <label><?= _("Vale 1") ?> <input name="vale0"></label>
        <label><?= _("Vale 2") ?> <input name="vale1"></label>
        <label><?= _("Cheque 1") ?> <input name="cheque0"></label>
        <label><?= _("Cheque 2") ?> <input name="cheque1"></label>
    </div>
    <?php endif; ?>
    <p class="arqueo-total" id="totales">
        <strong id="total-etiqueta"><?= $esBanco ? _("Saldo extracto:") : _("Total:") ?></strong> <span id="total-valor">0,00</span> €
        <span id="total-dif" class="arqueo-dif" aria-live="polite"><?= _("Diferencia:") ?> 0,00 €</span>
        <button type="button" id="btn-capuchinos" class="arqueo-capuchinos-btn" hidden><?= _("Buscar capuchinos") ?></button>
        <span class="arqueo-total-detalle" id="total-detalle"></span>
    </p>
    <button type="submit" id="btn-guardar-arqueo"><?= $esBanco ? _("Guardar saldo") : _("Guardar arqueo") ?></button>
    <p id="arqueo-msg" class="ok print-hide" hidden aria-live="polite"></p>
</form>
<div id="capuchinos-res" class="arqueo-capuchinos" hidden></div>
<script>
const I18N_ARQUEO = {
  saldoContable: <?= json_encode(_("Saldo contable (P+G) %s · P: %s · G: %s"), JSON_UNESCAPED_UNICODE) ?>,
  sinTesoreria: <?= json_encode(_("No hay ninguna tesorería activa de este tipo. Configúrela en Tesorería."), JSON_UNESCAPED_UNICODE) ?>,
  dineroVales: <?= json_encode(_("(dinero %s + vales %s)"), JSON_UNESCAPED_UNICODE) ?>,
  diferencia: <?= json_encode(_("Diferencia: %s €"), JSON_UNESCAPED_UNICODE) ?>,
  noMultiplo9: <?= json_encode(_("La diferencia no es múltiplo de 9."), JSON_UNESCAPED_UNICODE) ?>,
  capuchinos: <?= json_encode(_("Capuchinos (cifras invertidas)"), JSON_UNESCAPED_UNICODE) ?>,
  capuchinosAyuda: <?= json_encode(_("Si se escribieron dos cifras al revés (o se corrió la coma), la diferencia es múltiplo de 9. Apuntes de caja cuyo importe, cambiado así, explicaría el descuadre de %s €."), JSON_UNESCAPED_UNICODE) ?>,
  ningunApunte: <?= json_encode(_("Ningún apunte de caja encaja."), JSON_UNESCAPED_UNICODE) ?>,
  ver: <?= json_encode(_("Ver"), JSON_UNESCAPED_UNICODE) ?>,
  guardado: <?= json_encode(_("Arqueo guardado (%s €)."), JSON_UNESCAPED_UNICODE) ?>,
  guardadoSaldo: <?= json_encode(_("Saldo guardado (%s €)."), JSON_UNESCAPED_UNICODE) ?>,
  guardadoSimple: <?= json_encode(_("Arqueo guardado."), JSON_UNESCAPED_UNICODE) ?>,
  guardadoSaldoSimple: <?= json_encode(_("Saldo guardado."), JSON_UNESCAPED_UNICODE) ?>,
  errorGuardar: <?= json_encode(_("No se pudo guardar el arqueo"), JSON_UNESCAPED_UNICODE) ?>,
  errorGuardarSaldo: <?= json_encode(_("No se pudo guardar el saldo"), JSON_UNESCAPED_UNICODE) ?>,
  volver613: <?= json_encode(_("← Volver al resumen 613 %s"), JSON_UNESCAPED_UNICODE) ?>,
};
const TIPO_TESORERIA = <?= json_encode($tipoTesoreria, JSON_UNESCAPED_UNICODE) ?>;
const ES_CAJA = TIPO_TESORERIA === 'caja';
const BILS = [500,200,100,50,20,10,5];
const MON = [2,1,0.5,0.2,0.1,0.05,0.02,0.01];
let fisicaId = null;
let saldoContable = 0;

function fmtEuro(n) {
  return n.toLocaleString('es-ES', {
    useGrouping: true,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
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
  if (!ES_CAJA) {
    const total = parseImporte(fd.saldo_extracto);
    return { dinero: 0, vales: 0, total };
  }
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
  if (ES_CAJA && (dinero || vales)) {
    det.textContent = I18N_ARQUEO.dineroVales
      .replace('%s', fmtEuro(dinero))
      .replace('%s', fmtEuro(vales));
  } else {
    det.textContent = '';
  }
  const dif = total - saldoContable;
  const elDif = document.getElementById('total-dif');
  elDif.textContent = I18N_ARQUEO.diferencia.replace('%s', fmtEuro(dif));
  const ok = cents(dif) === 0;
  elDif.classList.toggle('error', !ok);
  elDif.classList.toggle('ok', ok);
  document.getElementById('totales').classList.toggle('alerta', !ok);
  const btn = document.getElementById('btn-capuchinos');
  const cap = Math.abs(cents(dif));
  btn.hidden = !ES_CAJA || !(cap > 0 && cap % 9 === 0);
  document.getElementById('capuchinos-res').hidden = true;
}

function grid(el, valores, prefix) {
  valores.forEach((v,i) => {
    const lab = document.createElement('label');
    lab.innerHTML = `${v} € <input type="number" min="0" step="1" name="${prefix}${i}" value="">`;
    el.appendChild(lab);
  });
}

function aplicarArqueoPrevio(r) {
  const arqueo = r?.arqueo;
  if (!arqueo) return;
  const form = document.getElementById('form-arq');
  if (arqueo.fecha) {
    form.querySelector('[name=fecha]').value = arqueo.fecha;
  }
  if (!ES_CAJA) {
    const d = arqueo.desglose || {};
    const raw = d.saldo_extracto ?? arqueo.total_es ?? arqueo.total ?? '';
    const inp = form.querySelector('[name=saldo_extracto]');
    if (inp && raw !== '') inp.value = String(raw).replace('.', ',');
    return;
  }
  const d = arqueo.desglose || {};
  (d.billetes || []).forEach((n, i) => {
    const inp = form.querySelector('[name=b' + i + ']');
    if (inp) inp.value = n;
  });
  (d.monedas || []).forEach((n, i) => {
    const inp = form.querySelector('[name=m' + i + ']');
    if (inp) inp.value = n;
  });
  const vales = d.vales || [];
  const cheques = d.cheques || [];
  ['vale0', 'vale1', 'cheque0', 'cheque1'].forEach((name, i) => {
    const val = i < 2 ? vales[i] : cheques[i - 2];
    const inp = form.querySelector('[name=' + name + ']');
    if (inp && val) inp.value = String(val).replace('.', ',');
  });
}

async function refreshSaldos() {
  if (!fisicaId) return;
  const r = await api('/api/arqueos/fisica/' + fisicaId);
  if (!r.ok) return alert(r.error);
  document.getElementById('saldos').textContent = I18N_ARQUEO.saldoContable
    .replace('%s', r.saldo_fisico_es || r.saldo_fisico)
    .replace('%s', r.saldo_caja_p)
    .replace('%s', r.saldo_caja_g);
  saldoContable = parseImporte(r.saldo_fisico);
  const form = document.getElementById('form-arq');
  aplicarArqueoPrevio(r);
  if (form) mostrarTotal(form);
}

function pintarCapuchinos(s) {
  const box = document.getElementById('capuchinos-res');
  box.hidden = false;
  if (!s.aplicable) {
    box.innerHTML = '<p class="muted">' + esc(I18N_ARQUEO.noMultiplo9) + '</p>';
    return;
  }
  const apuntes = s.apuntes || [];
  let html = '<h2>' + esc(I18N_ARQUEO.capuchinos) + '</h2>'
    + '<p class="muted">' + esc(I18N_ARQUEO.capuchinosAyuda.replace('%s', s.diferencia_es || s.diferencia)) + '</p>';
  if (!apuntes.length) {
    html += '<p>' + esc(I18N_ARQUEO.ningunApunte) + '</p>';
    box.innerHTML = html;
    return;
  }
  html += '<table><thead><tr>'
    + '<th><?= _("Fecha") ?></th><th>P/G</th><th><?= _("Inic.") ?></th><th><?= _("Concepto") ?></th>'
    + '<th><?= _("Observaciones") ?></th><th class="num"><?= _("Anotado") ?></th><th class="num"><?= _("Si fuera") ?></th>'
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
      + '<td><a href="' + href + '">' + esc(I18N_ARQUEO.ver) + '</a></td>'
      + '</tr>';
  });
  html += '</tbody></table>';
  box.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', async () => {
  const params = new URLSearchParams(location.search);
  if (params.get('from') === '613') {
    const libro = (params.get('libro') || 'p').toLowerCase();
    const volver = document.getElementById('arqueo-volver');
    const link = document.getElementById('arqueo-volver-link');
    link.href = '/613-' + libro;
    link.textContent = I18N_ARQUEO.volver613.replace('%s', libro.toUpperCase());
    volver.hidden = false;
  }

  if (ES_CAJA) {
    grid(document.getElementById('billetes'), BILS, 'b');
    grid(document.getElementById('monedas'), MON, 'm');
  }
  const form = document.getElementById('form-arq');
  form.addEventListener('input', () => mostrarTotal(form));

  form.querySelector('[name=fecha]').value = new Date().toISOString().slice(0, 10);
  const r = await api('/api/arqueos/tesoreria/' + TIPO_TESORERIA);
  if (!r.ok) return alert(r.error);
  const fisicas = r.fisicas_activas || [];
  if (!fisicas.length) {
    document.getElementById('sin-fisica').hidden = false;
    document.getElementById('sin-fisica').textContent = I18N_ARQUEO.sinTesoreria;
    document.getElementById('btn-guardar-arqueo').disabled = true;
    return;
  }
  fisicaId = r.cuenta_fisica_id || fisicas[0].id;
  if (fisicas.length > 1) {
    const wrap = document.getElementById('wrap-fisica');
    wrap.style.display = '';
    const sel = document.getElementById('sel-fisica');
    sel.innerHTML = fisicas.map((c) => `<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
    sel.value = fisicaId;
    sel.onchange = () => { fisicaId = parseInt(sel.value, 10); refreshSaldos(); };
  }
  await refreshSaldos();
  mostrarTotal(form);

  document.getElementById('btn-capuchinos').onclick = async () => {
    if (!ES_CAJA) return;
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
    if (!fisicaId) return;
    const btn = document.getElementById('btn-guardar-arqueo');
    const msg = document.getElementById('arqueo-msg');
    if (btn) btn.disabled = true;
    if (msg) msg.hidden = true;
    const fd = formObj(ev.target);
    let desglose;
    if (ES_CAJA) {
      desglose = {
        billetes: BILS.map((_,i) => fd['b'+i] || 0),
        monedas: MON.map((_,i) => fd['m'+i] || 0),
        vales: [fd.vale0, fd.vale1].filter(Boolean),
        cheques: [fd.cheque0, fd.cheque1].filter(Boolean),
      };
    } else {
      desglose = { saldo_extracto: fd.saldo_extracto || '0' };
    }
    const body = {fecha: fd.fecha, desglose};
    const s = await api('/api/arqueos/fisica/' + fisicaId, {method:'POST', body});
    if (btn) btn.disabled = false;
    if (!s.ok) {
      return alert(s.error || (ES_CAJA ? I18N_ARQUEO.errorGuardar : I18N_ARQUEO.errorGuardarSaldo));
    }
    if (msg) {
      const total = s.arqueo?.total_es || s.arqueo?.total || '';
      if (total) {
        msg.textContent = (ES_CAJA ? I18N_ARQUEO.guardado : I18N_ARQUEO.guardadoSaldo).replace('%s', total);
      } else {
        msg.textContent = ES_CAJA ? I18N_ARQUEO.guardadoSimple : I18N_ARQUEO.guardadoSaldoSimple;
      }
      msg.hidden = false;
    }
    mostrarTotal(form);
  };
});
</script>
