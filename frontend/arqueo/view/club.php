<?php if (empty($esClub)): ?>
<?php return; ?>
<?php endif; ?>
<h1><?= _("Arqueo") ?></h1>
<p class="muted"><?= _("Recuente caja y banco hasta una fecha. En la caja se cuentan billetes y monedas; el recuento se guarda con su fecha, cuadre o no. Si todo cuadra, márquelo: un cambio posterior de apuntes que mueva ese saldo avisará del descuadre.") ?></p>
<form id="form-arqueo" class="filters">
    <label><?= _("Fecha") ?> <input type="date" name="fecha" required></label>
</form>
<table id="tabla-arqueo">
    <thead>
    <tr>
        <th><?= _("Tesorería") ?></th>
        <th class="num"><?= _("Saldo contable") ?></th>
        <th class="num"><?= _("Contado") ?></th>
        <th class="num"><?= _("Diferencia") ?></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<div id="recuentos-caja"></div>
<p>
    <button type="button" id="btn-guardar-recuento" hidden><?= _("Guardar recuento") ?></button>
    <button type="button" id="btn-cuadrar" disabled><?= _("Dar por cuadrado") ?></button>
</p>
<p id="arqueo-msg" class="ok" hidden aria-live="polite"></p>
<h2><?= _("Recuentos anteriores") ?></h2>
<p class="muted" id="recuentos-vacio" hidden><?= _("Todavía no hay ningún recuento de caja guardado.") ?></p>
<table id="tabla-recuentos" hidden>
    <thead>
    <tr>
        <th><?= _("Fecha") ?></th>
        <th><?= _("Caja") ?></th>
        <th class="num"><?= _("Contado") ?></th>
        <th class="num"><?= _("Saldo contable") ?></th>
        <th class="num"><?= _("Diferencia") ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<h2><?= _("Periodos cuadrados") ?></h2>
<ul id="lista-cuadrados"></ul>
<script>
const I18N_ARQ = {
  cuadra: <?= json_encode(_("cuadra"), JSON_UNESCAPED_UNICODE) ?>,
  ninguno: <?= json_encode(_("Todavía no hay ningún periodo cuadrado."), JSON_UNESCAPED_UNICODE) ?>,
  guardado: <?= json_encode(_("Periodo cuadrado."), JSON_UNESCAPED_UNICODE) ?>,
  recuentoGuardado: <?= json_encode(_("Recuento del %s guardado."), JSON_UNESCAPED_UNICODE) ?>,
  recuentoDe: <?= json_encode(_("Recuento de %s"), JSON_UNESCAPED_UNICODE) ?>,
  billetes: <?= json_encode(_("Billetes"), JSON_UNESCAPED_UNICODE) ?>,
  monedas: <?= json_encode(_("Monedas"), JSON_UNESCAPED_UNICODE) ?>,
  vales: <?= json_encode(_("Vales / cheques (importe)"), JSON_UNESCAPED_UNICODE) ?>,
  vale: <?= json_encode(_("Vale %s"), JSON_UNESCAPED_UNICODE) ?>,
  cheque: <?= json_encode(_("Cheque %s"), JSON_UNESCAPED_UNICODE) ?>,
  total: <?= json_encode(_("Total:"), JSON_UNESCAPED_UNICODE) ?>,
  dineroVales: <?= json_encode(_("(dinero %s + vales %s)"), JSON_UNESCAPED_UNICODE) ?>,
  ver: <?= json_encode(_("Ver"), JSON_UNESCAPED_UNICODE) ?>,
};
const BILS = [500, 200, 100, 50, 20, 10, 5];
const MON = [2, 1, 0.5, 0.2, 0.1, 0.05, 0.02, 0.01];
const VALES = ['vale0', 'vale1', 'cheque0', 'cheque1'];
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-arqueo');
  const tb = document.querySelector('#tabla-arqueo tbody');
  const cajas = document.getElementById('recuentos-caja');
  const btn = document.getElementById('btn-cuadrar');
  const btnRecuento = document.getElementById('btn-guardar-recuento');
  const msg = document.getElementById('arqueo-msg');
  form.fecha.value = new Date().toISOString().slice(0, 10);
  let tesoreria = [];

  function centsDe(texto) {
    const s = String(texto || '').trim();
    if (!s) return null;
    const n = s.includes(',') ? Number(s.replace(/\./g, '').replace(',', '.')) : Number(s);
    return Number.isFinite(n) ? Math.round(n * 100) : null;
  }
  function fechaEs(f) {
    return f.slice(8, 10) + '/' + f.slice(5, 7) + '/' + f.slice(0, 4);
  }
  function campo(id, nombre) {
    return document.querySelector('#recuento-' + id + ' [name=' + nombre + ']');
  }

  /** Recuento de una caja tal como está escrito: cantidades por billete y moneda, vales y cheques. */
  function desgloseDe(id) {
    const texto = (n) => (campo(id, n).value || '').trim();
    return {
      billetes: BILS.map((_, i) => Number(texto('b' + i)) || 0),
      monedas: MON.map((_, i) => Number(texto('m' + i)) || 0),
      vales: ['vale0', 'vale1'].map(texto).filter(Boolean),
      cheques: ['cheque0', 'cheque1'].map(texto).filter(Boolean),
    };
  }
  /** Céntimos contados en una caja; null si no se ha escrito nada. */
  function contadoCaja(id) {
    let dinero = 0;
    let vales = 0;
    let algo = false;
    BILS.forEach((v, i) => {
      const t = campo(id, 'b' + i).value;
      if (t !== '') algo = true;
      dinero += Math.round((Number(t) || 0) * v * 100);
    });
    MON.forEach((v, i) => {
      const t = campo(id, 'm' + i).value;
      if (t !== '') algo = true;
      dinero += Math.round((Number(t) || 0) * v * 100);
    });
    VALES.forEach((n) => {
      const c = centsDe(campo(id, n).value);
      if (c !== null) { algo = true; vales += c; }
    });
    return algo ? { dinero, vales, total: dinero + vales } : null;
  }
  function contadoDe(t) {
    if (t.tipo === 'caja') {
      const c = contadoCaja(t.id);
      return c === null ? null : c.total;
    }
    return centsDe(document.getElementById('contado-' + t.id).value);
  }

  /** Recalcula contado, diferencia y totales sin reconstruir los campos (no pierde el foco). */
  function recalcular() {
    let cuadra = tesoreria.length > 0;
    tesoreria.forEach(t => {
      const contado = contadoDe(t);
      const dif = contado === null ? null : contado - t.saldo_cents;
      if (dif !== 0) cuadra = false;
      const difTd = document.getElementById('dif-' + t.id);
      difTd.textContent = dif === null ? '' : fmtImporteEs(dif / 100);
      difTd.classList.toggle('error', dif !== null && dif !== 0);
      if (t.tipo !== 'caja') return;
      document.getElementById('contado-' + t.id).textContent = contado === null ? '' : fmtImporteEs(contado / 100);
      const c = contadoCaja(t.id);
      document.getElementById('total-' + t.id).textContent = fmtImporteEs((c ? c.total : 0) / 100);
      document.getElementById('detalle-' + t.id).textContent = c && c.vales
        ? I18N_ARQ.dineroVales.replace('%s', fmtImporteEs(c.dinero / 100)).replace('%s', fmtImporteEs(c.vales / 100))
        : '';
    });
    btn.disabled = !cuadra;
  }

  function rejilla(valores, prefijo) {
    return '<div class="arqueo-grid">' + valores.map((v, i) =>
      '<label>' + esc(String(v).replace('.', ',')) + ' € <input type="number" min="0" step="1" name="' + prefijo + i + '"></label>'
    ).join('') + '</div>';
  }
  function panelCaja(t) {
    const box = document.createElement('fieldset');
    box.className = 'listado-seccion';
    box.id = 'recuento-' + t.id;
    box.innerHTML = '<legend>' + esc(I18N_ARQ.recuentoDe.replace('%s', t.nombre)) + '</legend>'
      + '<h3>' + esc(I18N_ARQ.billetes) + '</h3>' + rejilla(BILS, 'b')
      + '<h3>' + esc(I18N_ARQ.monedas) + '</h3>' + rejilla(MON, 'm')
      + '<h3>' + esc(I18N_ARQ.vales) + '</h3><div class="arqueo-grid">'
      + '<label>' + esc(I18N_ARQ.vale.replace('%s', '1')) + ' <input name="vale0" inputmode="decimal"></label>'
      + '<label>' + esc(I18N_ARQ.vale.replace('%s', '2')) + ' <input name="vale1" inputmode="decimal"></label>'
      + '<label>' + esc(I18N_ARQ.cheque.replace('%s', '1')) + ' <input name="cheque0" inputmode="decimal"></label>'
      + '<label>' + esc(I18N_ARQ.cheque.replace('%s', '2')) + ' <input name="cheque1" inputmode="decimal"></label>'
      + '</div>'
      + '<p class="arqueo-total"><strong>' + esc(I18N_ARQ.total) + '</strong> <span id="total-' + t.id + '">0,00</span> €'
      + ' <span class="arqueo-total-detalle" id="detalle-' + t.id + '"></span></p>';
    box.addEventListener('input', recalcular);
    return box;
  }
  /** Pone en los campos el recuento guardado ese día para la caja. */
  function aplicarRecuento(t) {
    const d = (t.recuento && t.recuento.desglose) || {};
    (d.billetes || []).forEach((n, i) => { if (Number(n)) campo(t.id, 'b' + i).value = n; });
    (d.monedas || []).forEach((n, i) => { if (Number(n)) campo(t.id, 'm' + i).value = n; });
    (d.vales || []).forEach((v, i) => { if (i < 2 && v) campo(t.id, 'vale' + i).value = String(v).replace('.', ','); });
    (d.cheques || []).forEach((v, i) => { if (i < 2 && v) campo(t.id, 'cheque' + i).value = String(v).replace('.', ','); });
  }

  function pintarTesoreria() {
    tb.replaceChildren();
    cajas.replaceChildren();
    tesoreria.forEach(t => {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + esc(t.nombre) + '</td><td class="num">' + esc(fmtImporteEs(t.saldo_cents / 100)) + '</td>'
        + (t.tipo === 'caja'
          ? '<td class="num" id="contado-' + t.id + '"></td>'
          : '<td class="num"><input id="contado-' + t.id + '" inputmode="decimal"></td>')
        + '<td class="num" id="dif-' + t.id + '"></td>';
      tb.appendChild(tr);
      if (t.tipo === 'caja') {
        cajas.appendChild(panelCaja(t));
        aplicarRecuento(t);
      } else {
        tr.querySelector('input').addEventListener('input', recalcular);
      }
    });
    btnRecuento.hidden = !tesoreria.some(t => t.tipo === 'caja');
    recalcular();
  }
  function pintarRecuentos(recuentos) {
    const tabla = document.getElementById('tabla-recuentos');
    const cuerpo = tabla.querySelector('tbody');
    cuerpo.replaceChildren();
    tabla.hidden = recuentos.length === 0;
    document.getElementById('recuentos-vacio').hidden = recuentos.length > 0;
    recuentos.forEach(r => {
      const dif = r.contado_cents - r.saldo_cents;
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + esc(fechaEs(r.fecha)) + '</td><td>' + esc(r.nombre) + '</td>'
        + '<td class="num">' + esc(fmtImporteEs(r.contado_cents / 100)) + '</td>'
        + '<td class="num">' + esc(fmtImporteEs(r.saldo_cents / 100)) + '</td>'
        + '<td class="num' + (dif !== 0 ? ' error' : '') + '">' + esc(dif === 0 ? I18N_ARQ.cuadra : fmtImporteEs(dif / 100)) + '</td>'
        + '<td class="acc"><button type="button" data-fecha="' + esc(r.fecha) + '">' + esc(I18N_ARQ.ver) + '</button></td>';
      cuerpo.appendChild(tr);
    });
  }
  function pintarCuadrados(cuadrados) {
    const ul = document.getElementById('lista-cuadrados');
    ul.replaceChildren();
    if (!cuadrados.length) {
      const li = document.createElement('li');
      li.textContent = I18N_ARQ.ninguno;
      ul.appendChild(li);
      return;
    }
    cuadrados.forEach(c => {
      const li = document.createElement('li');
      li.textContent = fechaEs(c.fecha) + ' — ' + (c.cuadra ? I18N_ARQ.cuadra : (c.avisos || []).join(' '));
      if (!c.cuadra) li.className = 'error';
      ul.appendChild(li);
    });
  }
  function pintar(r) {
    tesoreria = r.tesoreria || [];
    pintarTesoreria();
    pintarRecuentos(r.recuentos || []);
    pintarCuadrados(r.cuadrados || []);
  }
  function avisar(texto) {
    msg.textContent = texto;
    msg.hidden = false;
  }

  async function cargar() {
    msg.hidden = true;
    const r = await api('/api/arqueo-club?fecha=' + encodeURIComponent(form.fecha.value));
    if (!r.ok) { alert(r.error || 'Error'); return; }
    pintar(r);
  }
  /** Guarda el recuento de cada caja en la que se ha escrito algo. Devuelve el último estado, o null si falla. */
  async function guardarRecuentos() {
    let ultimo = { ok: true };
    for (const t of tesoreria) {
      if (t.tipo !== 'caja' || contadoCaja(t.id) === null) continue;
      ultimo = await api('/api/arqueo-club/recuento', { method: 'POST', body: {
        fecha: form.fecha.value, cuenta_fisica_id: t.id, desglose: desgloseDe(t.id),
      }});
      if (!ultimo.ok) { alert(ultimo.error || 'Error'); return null; }
    }
    return ultimo;
  }

  form.fecha.addEventListener('change', cargar);
  document.getElementById('tabla-recuentos').addEventListener('click', (ev) => {
    const b = ev.target.closest('button[data-fecha]');
    if (!b) return;
    form.fecha.value = b.dataset.fecha;
    cargar().then(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
  });
  btnRecuento.addEventListener('click', async () => {
    const r = await guardarRecuentos();
    if (!r) return;
    // Solo las listas: lo escrito (también el saldo del banco) se queda como está.
    if (r.recuentos) pintarRecuentos(r.recuentos);
    avisar(I18N_ARQ.recuentoGuardado.replace('%s', fechaEs(form.fecha.value)));
  });
  btn.addEventListener('click', async () => {
    const contados = {};
    tesoreria.forEach(t => { contados[t.id] = contadoDe(t); });
    if (!(await guardarRecuentos())) return;
    const r = await api('/api/arqueo-club/cuadrar', { method: 'POST', body: { fecha: form.fecha.value, contados } });
    if (!r.ok) { alert(r.error || 'Error'); return; }
    pintarCuadrados(r.cuadrados || []);
    const estado = await api('/api/arqueo-club?fecha=' + encodeURIComponent(form.fecha.value));
    if (estado.ok) pintarRecuentos(estado.recuentos || []);
    avisar(I18N_ARQ.guardado);
  });
  cargar();
});
</script>
