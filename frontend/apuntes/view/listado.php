<?php if (!empty($esCentroSg)): ?>
<h1><?= _("Apuntes") ?></h1>
<form id="filtros" class="filters">
    <label><?= _("Nombre") ?>
        <select name="iniciales">
            <option value=""><?= _("Todos") ?></option>
        </select>
    </label>
    <label><?= _("Concepto") ?>
        <select name="concepto">
            <option value=""><?= _("Todos") ?></option>
        </select>
    </label>
    <label><?= _("Desde") ?> <input type="date" name="desde"></label>
    <label><?= _("Hasta") ?> <input type="date" name="hasta"></label>
    <button type="submit"><?= _("Filtrar") ?></button>
</form>
<p id="msg-apuntes" class="muted" hidden></p>
<table id="tabla-apuntes">
    <thead>
    <tr>
        <th><?= _("Fecha") ?></th>
        <th><?= _("Nombre") ?></th>
        <th><?= _("Concepto") ?></th>
        <th><?= _("Observaciones") ?></th>
        <th class="num"><?= _("Cantidad") ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<script>
const I18N_APUNTES_SG = {
  error: <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>,
  vacio: <?= json_encode(_("No hay apuntes con estos filtros."), JSON_UNESCAPED_UNICODE) ?>,
};
const nombres = new Map();
const conceptos = new Map();
function nombreDe(ini) {
  return nombres.get(ini) || ini || '';
}
function conceptoDe(cod) {
  const n = conceptos.get(cod);
  return n ? cod + ' ' + n : (cod || '');
}
async function loadApuntesSg() {
  const form = document.getElementById('filtros');
  const q = new URLSearchParams(formObj(form));
  q.set('cuenta', 'G');
  const r = await api('/api/apuntes?' + q.toString());
  const tb = document.querySelector('#tabla-apuntes tbody');
  const msg = document.getElementById('msg-apuntes');
  tb.innerHTML = '';
  if (!r.ok) {
    msg.hidden = false;
    msg.textContent = r.error || I18N_APUNTES_SG.error;
    return;
  }
  const apuntes = r.apuntes || [];
  msg.hidden = apuntes.length > 0;
  msg.textContent = apuntes.length ? '' : I18N_APUNTES_SG.vacio;
  apuntes.forEach((a) => {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td>' + esc(a.fecha_es || a.fecha) + '</td><td>' + esc(nombreDe(a.iniciales || '')) + '</td><td>'
      + esc(conceptoDe(a.concepto_codigo)) + '</td><td>' + esc(a.observaciones || '') + '</td><td class="num">'
      + esc(a.cantidad_es) + '</td><td class="col-acc">' + accionesApunteHtml(a) + '</td>';
    enlazarAccionesApunte(tr, a, loadApuntesSg);
    tb.appendChild(tr);
  });
}
document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('filtros');
  const [pers, cons] = await Promise.all([
    api('/api/personas'),
    api('/api/conceptos?cuenta=G'),
  ]);
  if (!pers.ok) return alert(pers.error || I18N_APUNTES_SG.error);
  const selN = form.querySelector('[name=iniciales]');
  (pers.personas || []).forEach((p) => {
    const etiqueta = [p.apellidos, p.nombre].filter(Boolean).join(', ') || p.iniciales;
    nombres.set(p.iniciales, etiqueta);
    const o = document.createElement('option');
    o.value = p.iniciales;
    o.textContent = etiqueta;
    selN.appendChild(o);
  });
  const selC = form.querySelector('[name=concepto]');
  (cons.conceptos || []).forEach((c) => {
    conceptos.set(c.codigo, c.nombre || '');
    const o = document.createElement('option');
    o.value = c.codigo;
    o.textContent = c.codigo + ' ' + (c.nombre || '');
    selC.appendChild(o);
  });
  form.onsubmit = (ev) => {
    ev.preventDefault();
    loadApuntesSg();
  };
  loadApuntesSg();
});
</script>
<?php elseif (!empty($esClub)): ?>
<h1><?= _("Apuntes") ?></h1>
<form id="filtros" class="filters">
    <label><?= _("Ejercicio") ?>
        <select name="ejercicio" id="sel-ejercicio"></select>
    </label>
    <input type="date" name="desde">
    <input type="date" name="hasta">
    <label><?= _("Cuenta") ?>
        <select name="qcuenta" id="sel-filtro-cuenta">
            <option value=""><?= _("Todas") ?></option>
        </select>
    </label>
    <label><?= _("Tesorería") ?>
        <select name="qtesoreria" id="sel-filtro-tesoreria">
            <option value=""><?= _("Todas") ?></option>
        </select>
    </label>
    <input name="qglosa" placeholder="<?= htmlspecialchars(_("Observaciones"), ENT_QUOTES) ?>">
    <button type="submit"><?= _("Filtrar") ?></button>
</form>
<hr class="separa-apunte">
<form id="form-apunte" class="grid-form" hidden>
    <input type="hidden" name="id" value="">
    <label><?= _("Fecha") ?> <input name="fecha" type="date" required></label>
    <label><?= _("Cuenta") ?> <select name="cuenta_id" id="sel-cuenta"></select></label>
    <label><?= _("Observaciones") ?> <input name="glosa"></label>
    <label><?= _("Cantidad") ?> <input name="importe" required></label>
    <button type="submit"><?= _("Guardar") ?></button>
    <button type="button" id="btn-cancelar-apunte"><?= _("Cancelar") ?></button>
</form>
<p id="total-apuntes" class="muted"></p>
<table id="tabla-apuntes">
    <thead>
    <tr>
        <th data-ord="fecha"><?= _("Fecha") ?></th>
        <th data-ord="cuenta"><?= _("Cuenta") ?></th>
        <th data-ord="nombre"><?= _("Nombre") ?></th>
        <th data-ord="glosa"><?= _("Observaciones") ?></th>
        <th class="num" data-ord="cents"><?= _("Cantidad") ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<script>
const I18N_APUNTES = {
  modificar: <?= json_encode(_("Modificar"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  confirm: <?= json_encode(_("¿Borrar este apunte?"), JSON_UNESCAPED_UNICODE) ?>,
  total: <?= json_encode(_("Total"), JSON_UNESCAPED_UNICODE) ?>,
  otros: <?= json_encode(_("No hay apuntes en este ejercicio. Hay en: "), JSON_UNESCAPED_UNICODE) ?>,
  todas: <?= json_encode(_("Todas"), JSON_UNESCAPED_UNICODE) ?>,
};
let movimientos = [];
let cuentas = [];
let orden = { campo: 'fecha', desc: false };
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('filtros');
  const tb = document.querySelector('#tabla-apuntes tbody');
  const sel = document.getElementById('sel-ejercicio');
  const formApunte = document.getElementById('form-apunte');
  function encajaCuenta(codigo, filtro) {
    if (!filtro) return true;
    return codigo === filtro || codigo.startsWith(filtro + '.');
  }
  function visibles() {
    const cuenta = form.qcuenta.value || '';
    const glosa = (form.qglosa.value || '').trim().toLowerCase();
    const lista = movimientos.filter(m => {
      if (!encajaCuenta(m.codigo || '', cuenta)) return false;
      if (form.qtesoreria.value && (m.tesoreria || '') !== form.qtesoreria.value) return false;
      if (glosa && !(m.glosa || '').toLowerCase().includes(glosa)) return false;
      return true;
    });
    const campo = orden.campo;
    lista.sort((a, b) => {
      const va = campo === 'cuenta' ? a.codigo : a[campo];
      const vb = campo === 'cuenta' ? b.codigo : b[campo];
      const cmp = campo === 'cents' ? va - vb : String(va).localeCompare(String(vb), 'es');
      return orden.desc ? -cmp : cmp;
    });
    return lista;
  }
  function pintar() {
    const lista = visibles();
    tb.replaceChildren();
    if (lista.length === 0 && movimientos.length === 0) {
      const otros = form.dataset.otros || '';
      if (otros) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="6">' + esc(I18N_APUNTES.otros + otros) + '</td>';
        tb.appendChild(tr);
      }
    }
    let total = 0;
    lista.forEach(m => {
      total += m.cents || 0;
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>' + esc(m.fecha) + '</td><td>' + esc(m.codigo) + '</td><td>' + esc(m.nombre) + '</td><td>'
        + esc(m.glosa) + '</td><td class="num">' + esc(m.importe) + '</td><td class="acc">'
        + '<button type="button" data-act="editar" data-id="' + m.id + '">' + esc(I18N_APUNTES.modificar) + '</button> '
        + '<button type="button" data-act="borrar" data-id="' + m.id + '">' + esc(I18N_APUNTES.borrar) + '</button></td>';
      tb.appendChild(tr);
    });
    document.getElementById('total-apuntes').textContent = lista.length
      ? I18N_APUNTES.total + ' ' + fmtImporteEs(total / 100)
      : '';
  }
  function llenarCuentas(seleccion) {
    const box = document.getElementById('sel-cuenta');
    box.replaceChildren();
    cuentas.forEach(c => {
      if (c.grupo) return;
      const o = document.createElement('option');
      o.value = c.id;
      o.textContent = c.codigo + ' ' + c.nombre;
      box.appendChild(o);
    });
    if (seleccion) box.value = String(seleccion);
  }
  async function cargar() {
    const datos = formObj(form);
    if (datos.desde || datos.hasta) delete datos.ejercicio;
    delete datos.qcuenta;
    delete datos.qtesoreria;
    delete datos.qglosa;
    const r = await api('/api/grisbi/movimientos?' + new URLSearchParams(datos).toString());
    if (!r.ok) { alert(r.error || 'Error'); return; }
    movimientos = r.movimientos || [];
    cuentas = r.cuentas || cuentas;
    form.dataset.otros = (r.otros || []).join(', ');
    llenarFiltroCuenta();
    llenarFiltroTesoreria();
    pintar();
  }
  function llenarFiltroTesoreria() {
    const box = document.getElementById('sel-filtro-tesoreria');
    const elegido = box.value;
    const nombres = [...new Set(movimientos.map(m => m.tesoreria).filter(Boolean))]
      .sort((a, b) => a.localeCompare(b, 'es'));
    box.replaceChildren();
    const todas = document.createElement('option');
    todas.value = '';
    todas.textContent = I18N_APUNTES.todas;
    box.appendChild(todas);
    nombres.forEach(nombre => {
      const o = document.createElement('option');
      o.value = nombre;
      o.textContent = nombre;
      box.appendChild(o);
    });
    if (nombres.includes(elegido)) box.value = elegido;
  }
  function llenarFiltroCuenta() {
    const box = document.getElementById('sel-filtro-cuenta');
    const elegido = box.value;
    const porCodigo = new Map(cuentas.map(c => [c.codigo, c]));
    const codigos = new Set();
    movimientos.forEach(m => { if (m.codigo) codigos.add(m.codigo); });
    [...codigos].forEach(codigo => {
      const partes = codigo.split('.');
      if (!/^(60|61|70|80|90)$/.test(partes[0])) return;
      let prefijo = '';
      for (let i = 0; i < partes.length - 1; i++) {
        prefijo = prefijo ? prefijo + '.' + partes[i] : partes[i];
        codigos.add(prefijo);
      }
    });
    const ordenados = [...codigos].sort((a, b) => a.localeCompare(b, 'es', { numeric: true }));
    box.replaceChildren();
    const todas = document.createElement('option');
    todas.value = '';
    todas.textContent = I18N_APUNTES.todas;
    box.appendChild(todas);
    ordenados.forEach(codigo => {
      const c = porCodigo.get(codigo);
      const o = document.createElement('option');
      o.value = codigo;
      o.textContent = c && c.nombre ? codigo + ' ' + c.nombre : codigo;
      box.appendChild(o);
    });
    if (ordenadoIncluye(ordenados, elegido)) box.value = elegido;
  }
  function ordenadoIncluye(lista, valor) {
    return valor !== '' && lista.includes(valor);
  }
  form.addEventListener('submit', (ev) => { ev.preventDefault(); cargar(); });
  form.qcuenta.addEventListener('change', pintar);
  form.qtesoreria.addEventListener('change', pintar);
  form.qglosa.addEventListener('input', pintar);
  document.querySelector('#tabla-apuntes thead').addEventListener('click', (ev) => {
    const th = ev.target.closest('th[data-ord]');
    if (!th) return;
    if (orden.campo === th.dataset.ord) orden.desc = !orden.desc;
    else { orden.campo = th.dataset.ord; orden.desc = false; }
    pintar();
  });
  document.getElementById('btn-cancelar-apunte').onclick = () => {
    formApunte.reset();
    formApunte.id.value = '';
    formApunte.cuenta_id.disabled = false;
    formApunte.hidden = true;
  };
  formApunte.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const f = ev.target;
    const s = await api('/api/grisbi/movimientos/' + f.id.value, {
      method: 'POST',
      body: { fecha: f.fecha.value, cuenta_id: Number(f.cuenta_id.value), glosa: f.glosa.value, importe: f.importe.value },
    });
    if (!s.ok) return alert(s.error || 'Error');
    if ((s.avisos || []).length) alert(s.avisos.join('\n'));
    f.hidden = true;
    cargar();
  });
  tb.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('button');
    if (!btn) return;
    const m = movimientos.find(x => x.id === Number(btn.dataset.id));
    if (!m) return;
    if (btn.dataset.act === 'editar') {
      formApunte.hidden = false;
      formApunte.id.value = m.id;
      formApunte.fecha.value = m.fecha;
      formApunte.glosa.value = m.glosa || '';
      formApunte.importe.value = fmtImporteEs(Math.abs(m.cents || 0) / 100);
      llenarCuentas(m.cuenta_id);
      if (!m.cuenta_id) formApunte.cuenta_id.disabled = true;
      else formApunte.cuenta_id.disabled = false;
      formApunte.fecha.focus();
    }
    if (btn.dataset.act === 'borrar') {
      if (!confirm(I18N_APUNTES.confirm)) return;
      const s = await api('/api/grisbi/movimientos/' + m.id + '/borrar', { method: 'POST', body: {} });
      if (!s.ok) return alert(s.error || 'Error');
      if ((s.avisos || []).length) alert(s.avisos.join('\n'));
      cargar();
    }
  });
  api('/api/ejercicios').then(r => {
    (r.ejercicios || []).forEach(e => {
      const o = document.createElement('option');
      o.value = e.id;
      o.textContent = e.etiqueta;
      if (e.estado === 'abierto') o.selected = true;
      sel.appendChild(o);
    });
    cargar();
  });
});
</script>
<?php else: ?>
<h1><?= _("Apuntes") ?></h1>
<p class="print-hide arqueo-volver" id="apuntes-volver" hidden>
    <a href="#"><?= _("← Volver") ?></a>
</p>
<form id="filtros" class="filters">
    <select name="cuenta"><option value=""><?= _("P y G") ?></option><option>P</option><option>G</option></select>
    <select name="origen"><option value="">A/B/C</option><option>A</option><option>B</option><option>C</option></select>
    <select name="iniciales"><option value=""><?= _("Iniciales") ?></option></select>
    <input name="concepto" placeholder="<?= htmlspecialchars(_("Concepto"), ENT_QUOTES) ?>">
    <input type="date" name="desde">
    <input type="date" name="hasta">
    <button type="submit"><?= _("Filtrar") ?></button>
</form>
<p class="muted print-hide"><?= _("Orden y filtros por fecha de imputación (613 e informes). Si la operación fue otro día, se indica entre paréntesis.") ?></p>
<p id="apuntes-cuadre-ayuda" class="muted" hidden>
    <?= _("Gasto suma, ingreso resta. El saldo de cada fecha debería ser 0; las fechas que no cuadran se marcan para localizar el desajuste.") ?>
</p>
<p id="apuntes-cuadre-total" class="apuntes-cuadre-total" hidden></p>
<table id="tabla-apuntes">
    <thead>
    <tr>
        <th><?= _("Fecha") ?></th>
        <th>P/G</th>
        <th>A/B/C</th>
        <th><?= _("Inic.") ?></th>
        <th><?= _("Concepto") ?></th>
        <th><?= _("Observaciones") ?></th>
        <th class="num"><?= _("Cantidad") ?></th>
        <th class="num col-efecto" hidden><?= _("Efecto") ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<script>
const I18N_APUNTES = {
  error: <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>,
  verEnG: <?= json_encode(_("Ver en G"), JSON_UNESCAPED_UNICODE) ?>,
  aceptar21: <?= json_encode(_("Aceptar (P/211 y G/11)"), JSON_UNESCAPED_UNICODE) ?>,
  aceptar111: <?= json_encode(_("Aceptar (ingreso P/111)"), JSON_UNESCAPED_UNICODE) ?>,
  cuadra: <?= json_encode(_("cuadra"), JSON_UNESCAPED_UNICODE) ?>,
  noCuadra: <?= json_encode(_("no cuadra"), JSON_UNESCAPED_UNICODE) ?>,
  saldoDia: <?= json_encode(_("· saldo del día "), JSON_UNESCAPED_UNICODE) ?>,
  saldoA: <?= json_encode(_("Saldo A de "), JSON_UNESCAPED_UNICODE) ?>,
  cuadrado: <?= json_encode(_(" (cuadrado)."), JSON_UNESCAPED_UNICODE) ?>,
  fechasSinCuadrar: <?= json_encode(_(" · %s fecha(s) sin cuadrar."), JSON_UNESCAPED_UNICODE) ?>,
  op: <?= json_encode(_("(op. "), JSON_UNESCAPED_UNICODE) ?>,
  sugEnG: <?= json_encode(_("Sugerencia: en G hay un %s."), JSON_UNESCAPED_UNICODE) ?>,
  sugDevolucion: <?= json_encode(_(" Es una devolución. En P sobran gastos por esa cantidad; falta un ingreso P/A 111 (el 111 de ese día no incluye este abono)."), JSON_UNESCAPED_UNICODE) ?>,
  sugFaltaGasto: <?= json_encode(_(" Falta el gasto P/A de esa cantidad (el 111 de ese día parece incluirlo)."), JSON_UNESCAPED_UNICODE) ?>,
  sugVivienda21: <?= json_encode(_(" Si imputa a generales, debería haber un P/211 por el mismo importe."), JSON_UNESCAPED_UNICODE) ?>,
  sugPareja: <?= json_encode(_(" Puede faltar el apunte P/A pareja."), JSON_UNESCAPED_UNICODE) ?>,
  confirmVivienda: <?= json_encode(_("Se anotará un gasto P/A %s de %s € el %s y, si imputa a generales (211), el ingreso G/A 11."), JSON_UNESCAPED_UNICODE) ?>,
  p21g11Fallo: <?= json_encode(_("P/211 creado, pero G/11 falló: %s"), JSON_UNESCAPED_UNICODE) ?>,
  confirm111: <?= json_encode(_("Se anotará un ingreso P/A 111 de %s € el %s (contrapartida de la devolución en G). El apunte G no se toca."), JSON_UNESCAPED_UNICODE) ?>,
};
function parseImporte(raw) {
  if (raw === null || raw === undefined || raw === '') return 0;
  const n = Number(String(raw).replace(',', '.'));
  return Number.isFinite(n) ? n : 0;
}

function fmtEuro(n) {
  return n.toLocaleString(secretaryLocale(), {
    useGrouping: true,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function aplicarFiltrosUrl(form) {
  const q = new URLSearchParams(location.search);
  ['cuenta', 'origen', 'iniciales', 'concepto', 'desde', 'hasta'].forEach((k) => {
    const v = q.get(k);
    const el = form.querySelector('[name="' + k + '"]');
    if (el && v !== null) el.value = v;
  });
}

function modoCuadreA(form) {
  const fd = formObj(form);
  return fd.cuenta === 'P' && fd.origen === 'A' && String(fd.iniciales || '').trim() !== '';
}

function signedImporte(a, naturalezas) {
  const nat = naturalezas[a.concepto_codigo] || '';
  const n = parseImporte(a.cantidad);
  if (nat === 'gasto') return n;
  if (nat === 'ingreso') return -n;
  return 0;
}

function cents(n) {
  return Math.round(n * 100);
}

function mismaCantidad(a, importe) {
  return cents(Math.abs(parseImporte(a.cantidad))) === cents(Math.abs(importe));
}

function etiquetaApunte(a, naturalezas) {
  const nat = naturalezas[a.concepto_codigo] || '';
  const n = parseImporte(a.cantidad);
  let tipo = nat === 'gasto' ? 'gasto' : (nat === 'ingreso' ? 'ingreso' : 'apunte');
  if (nat === 'gasto' && n < 0) tipo = 'abono/devolución';
  const obs = a.observaciones ? ' «' + a.observaciones + '»' : '';
  return tipo + ' G/' + a.origen + ' ' + a.concepto_codigo + obs + ' de ' + fmtEuro(Math.abs(n)) + ' €';
}

function destinoVolver() {
  const raw = new URLSearchParams(location.search).get('from');
  if (raw && /^\/[A-Za-z0-9/?=&._%-]*$/.test(raw) && !raw.startsWith('//')) {
    return raw;
  }
  return '';
}

function queryConFrom(form) {
  const q = new URLSearchParams(formObj(form));
  const from = new URLSearchParams(location.search).get('from');
  if (from) q.set('from', from);
  return q;
}

function mostrarVolver() {
  const wrap = document.getElementById('apuntes-volver');
  const a = wrap.querySelector('a');
  const dest = destinoVolver();
  if (dest) {
    a.href = dest;
    a.onclick = null;
    wrap.hidden = false;
    return;
  }
  if (history.length > 1) {
    a.href = '#';
    a.onclick = (ev) => { ev.preventDefault(); history.back(); };
    wrap.hidden = false;
    return;
  }
  wrap.hidden = true;
}

function fechaImputacion(a) {
  return a.fecha_imputacion || a.fecha || '';
}

function fechaImputacionEs(a) {
  return a.fecha_imputacion_es || a.fecha_es || fechaImputacion(a);
}

function fechasDistintas(a) {
  return !!(a.fecha_imputacion && a.fecha_imputacion !== a.fecha);
}

function candidatoSugerencia(saldoDia, fecha, apuntesG, natG) {
  const objetivo = Math.abs(saldoDia);
  if (cents(objetivo) === 0) return null;
  const delDia = (apuntesG || []).filter((a) => fechaImputacion(a) === fecha);
  const exactos = delDia.filter((a) => mismaCantidad(a, objetivo));
  const gastos = exactos.filter((a) => (natG[a.concepto_codigo] || '') === 'gasto');
  const candidatos = (gastos.length ? gastos : exactos).slice();
  candidatos.sort((x, y) => (x.origen === 'A' ? 0 : 1) - (y.origen === 'A' ? 0 : 1));
  return candidatos[0] || null;
}

function mensajeSugerencia(saldoDia, a, natG) {
  let msg = I18N_APUNTES.sugEnG.replace('%s', etiquetaApunte(a, natG));
  if ((natG[a.concepto_codigo] || '') === 'gasto' && parseImporte(a.cantidad) < 0) {
    msg += I18N_APUNTES.sugDevolucion;
  } else if ((natG[a.concepto_codigo] || '') === 'gasto' && saldoDia < 0) {
    msg += I18N_APUNTES.sugFaltaGasto;
  } else if ((natG[a.concepto_codigo] || '') === 'ingreso') {
    msg += I18N_APUNTES.sugVivienda21;
  } else {
    msg += I18N_APUNTES.sugPareja;
  }
  return msg;
}

function sePuedeAceptar21(saldoDia, a, natG) {
  const n = parseImporte(a.cantidad);
  if (n <= 0) return false;
  const nat = natG[a.concepto_codigo] || '';
  if (nat === 'ingreso') return true;
  return nat === 'gasto' && saldoDia < 0;
}

function sePuedeAceptar111(saldoDia, a, natG) {
  const n = parseImporte(a.cantidad);
  return (natG[a.concepto_codigo] || '') === 'gasto' && n < 0 && saldoDia > 0;
}

function cantidadPositiva(a) {
  return (Math.abs(parseImporte(a.cantidad))).toFixed(2);
}

function urlVerG(a, fecha) {
  const q = new URLSearchParams({
    cuenta: 'G',
    origen: a.origen || 'A',
    iniciales: a.iniciales || '',
    desde: fecha,
    hasta: fecha,
    from: '/apuntes?' + queryConFrom(document.getElementById('filtros')).toString(),
  });
  return '/apuntes?' + q.toString();
}

async function aceptarSugerencia21(a) {
  const cant = a.cantidad;
  const fecha = a.fecha;
  const ini = a.iniciales || '';
  const obs = a.observaciones || '';
  const pers = await api('/api/personas');
  const persona = (pers.personas || []).find((x) => x.iniciales === ini);
  const aporta = persona ? !!persona.vivienda_aporta_generales : true;
  const esG11 = a.cuenta === 'G' && a.concepto_codigo === '11';
  const concepto = (aporta || esG11) ? '211' : '212';
  const txt = I18N_APUNTES.confirmVivienda
    .replace('%s', concepto)
    .replace('%s', fmtEuro(parseImporte(cant)))
    .replace('%s', a.fecha_es || fecha);
  if (!confirm(txt)) return;
  const base = {
    fecha,
    origen: 'A',
    iniciales: ini,
    observaciones: obs,
    cantidad: cantidadPositiva(a),
  };
  const p = await api('/api/apuntes', {
    method: 'POST',
    body: Object.assign({ cuenta: 'P', concepto_codigo: concepto }, base),
  });
  if (!p.ok) return alert(p.error);
  if (concepto === '211' && !esG11) {
    const g = await api('/api/apuntes', {
      method: 'POST',
      body: Object.assign({ cuenta: 'G', concepto_codigo: '11' }, base),
    });
    if (!g.ok) return alert(I18N_APUNTES.p21g11Fallo.replace('%s', g.error));
  }
  loadApuntes();
}

async function aceptarSugerencia111(a) {
  const cant = cantidadPositiva(a);
  const fecha = a.fecha;
  const ini = a.iniciales || '';
  const obs = a.observaciones || '';
  const txt = I18N_APUNTES.confirm111
    .replace('%s', fmtEuro(parseImporte(cant)))
    .replace('%s', a.fecha_es || fecha);
  if (!confirm(txt)) return;
  const s = await api('/api/apuntes', {
    method: 'POST',
    body: {
      fecha,
      cuenta: 'P',
      origen: 'A',
      iniciales: ini,
      concepto_codigo: '111',
      observaciones: obs,
      cantidad: cant,
    },
  });
  if (!s.ok) return alert(s.error);
  loadApuntes();
}

function agruparPorFecha(apuntes) {
  const grupos = [];
  apuntes.forEach((a) => {
    const f = fechaImputacion(a);
    if (!grupos.length || grupos[grupos.length - 1].fecha !== f) {
      grupos.push({ fecha: f, fecha_es: fechaImputacionEs(a), apuntes: [] });
    }
    grupos[grupos.length - 1].apuntes.push(a);
  });
  return grupos;
}

function filaApunte(a, efecto, conEfecto) {
  const tr = document.createElement('tr');
  if (a.es_cierre) tr.classList.add('cierre');
  const efectoTd = conEfecto
    ? `<td class="num">${efecto === 0 ? '' : esc(fmtEuro(efecto))}</td>`
    : '';
  tr.innerHTML = `<td>${fechaCelda(a)}</td><td>${esc(a.cuenta)}</td><td>${esc(a.origen)}</td>
    <td>${esc(a.iniciales || '')}</td><td>${esc(a.concepto_codigo)}</td>
    <td>${esc(a.observaciones || '')}</td><td class="num">${esc(a.cantidad_es)}</td>
    ${efectoTd}
    <td class="col-acc">${accionesApunteHtml(a)}</td>`;
  enlazarAccionesApunte(tr, a, loadApuntes);
  return tr;
}

async function loadApuntes() {
  const form = document.getElementById('filtros');
  const q = new URLSearchParams(formObj(form));
  const diagnostic = modoCuadreA(form);
  document.getElementById('apuntes-cuadre-ayuda').hidden = !diagnostic;
  document.querySelectorAll('.col-efecto').forEach((el) => { el.hidden = !diagnostic; });

  const r = await api('/api/apuntes?' + q.toString());
  const tb = document.querySelector('#tabla-apuntes tbody');
  tb.innerHTML = '';

  let naturalezas = {};
  let natG = {};
  let apuntesG = [];
  if (diagnostic) {
    const fd = formObj(form);
    const qG = new URLSearchParams({
      cuenta: 'G',
      iniciales: String(fd.iniciales || '').trim(),
    });
    if (fd.desde) qG.set('desde', fd.desde);
    if (fd.hasta) qG.set('hasta', fd.hasta);
    const [cP, cG, rG] = await Promise.all([
      api('/api/conceptos?cuenta=P'),
      api('/api/conceptos?cuenta=G'),
      api('/api/apuntes?' + qG.toString()),
    ]);
    (cP.conceptos || []).forEach((x) => { naturalezas[x.codigo] = x.naturaleza; });
    (cG.conceptos || []).forEach((x) => { natG[x.codigo] = x.naturaleza; });
    apuntesG = rG.apuntes || [];
  }

  const apuntes = r.apuntes || [];
  const totalEl = document.getElementById('apuntes-cuadre-total');

  if (!diagnostic) {
    totalEl.hidden = true;
    apuntes.forEach((a) => tb.appendChild(filaApunte(a, 0, false)));
    return;
  }

  let total = 0;
  let nFail = 0;
  agruparPorFecha(apuntes).forEach((g) => {
    let saldoDia = 0;
    g.apuntes.forEach((a) => { saldoDia += signedImporte(a, naturalezas); });
    total += saldoDia;
    const ok = Math.abs(saldoDia) < 0.005;
    if (!ok) nFail += 1;
    const cand = ok ? null : candidatoSugerencia(saldoDia, g.fecha, apuntesG, natG);
    const cab = document.createElement('tr');
    cab.className = 'apuntes-dia ' + (ok ? 'apuntes-dia-ok' : 'apuntes-dia-fail');
    const td = document.createElement('td');
    td.colSpan = 9;
    td.innerHTML = `<strong>${esc(g.fecha_es)}</strong>
      ${esc(I18N_APUNTES.saldoDia)}<span class="num">${esc(fmtEuro(saldoDia))}</span>
      ${ok ? '<span class="ok">' + esc(I18N_APUNTES.cuadra) + '</span>' : '<span class="error">' + esc(I18N_APUNTES.noCuadra) + '</span>'}`;
    if (cand) {
      const sug = document.createElement('span');
      sug.className = 'apuntes-dia-sug';
      const txt = document.createElement('span');
      txt.textContent = mensajeSugerencia(saldoDia, cand, natG) + ' ';
      sug.appendChild(txt);
      const ver = document.createElement('a');
      ver.href = urlVerG(cand, g.fecha);
      ver.textContent = I18N_APUNTES.verEnG;
      sug.appendChild(ver);
      if (sePuedeAceptar21(saldoDia, cand, natG)) {
        sug.appendChild(document.createTextNode(' · '));
        const acc = document.createElement('button');
        acc.type = 'button';
        acc.textContent = I18N_APUNTES.aceptar21;
        acc.onclick = () => aceptarSugerencia21(cand);
        sug.appendChild(acc);
      } else if (sePuedeAceptar111(saldoDia, cand, natG)) {
        sug.appendChild(document.createTextNode(' · '));
        const acc = document.createElement('button');
        acc.type = 'button';
        acc.textContent = I18N_APUNTES.aceptar111;
        acc.onclick = () => aceptarSugerencia111(cand);
        sug.appendChild(acc);
      }
      td.appendChild(sug);
    }
    cab.appendChild(td);
    tb.appendChild(cab);
    g.apuntes.forEach((a) => tb.appendChild(filaApunte(a, signedImporte(a, naturalezas), true)));
  });
  totalEl.hidden = false;
  const okTotal = Math.abs(total) < 0.005;
  totalEl.className = 'apuntes-cuadre-total ' + (okTotal ? 'ok' : 'error');
  totalEl.textContent = I18N_APUNTES.saldoA + String(formObj(form).iniciales).trim()
    + ': ' + fmtEuro(total)
    + (okTotal ? I18N_APUNTES.cuadrado : I18N_APUNTES.fechasSinCuadrar.replace('%s', nFail));
}

async function cargarPersonasFiltro(form) {
  const sel = form.querySelector('[name=iniciales]');
  const r = await api('/api/personas');
  if (!r.ok) return alert(r.error || I18N_APUNTES.error);
  (r.personas || []).forEach((p) => {
    const o = document.createElement('option');
    o.value = p.iniciales;
    o.textContent = (p.nombre_completo || p.nombre || p.iniciales) + ' (' + p.iniciales + ')';
    sel.appendChild(o);
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  const form = document.getElementById('filtros');
  await cargarPersonasFiltro(form);
  aplicarFiltrosUrl(form);
  mostrarVolver();
  form.onsubmit = (ev) => {
    ev.preventDefault();
    history.replaceState(null, '', '/apuntes?' + queryConFrom(form).toString());
    loadApuntes();
  };
  loadApuntes();
});
function fechaCelda(a) {
  if (fechasDistintas(a)) {
    return esc(fechaImputacionEs(a)) + ' <span class="muted">' + esc(I18N_APUNTES.op) + esc(a.fecha_es) + ')</span>';
  }
  return esc(a.fecha_es);
}
</script>
<?php endif; ?>
