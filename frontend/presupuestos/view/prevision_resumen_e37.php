<style>
@page { size: A4 landscape; margin: 8mm; }
</style>
<h1 class="print-hide"><?= _("Previsión resumen E37") ?></h1>
<p class="muted print-hide"><?= _("Cuadrícula como Cuentas personales (E37): una fila de totales por persona, con los importes guardados en la previsión personal del año elegido.") ?></p>
<p id="msg-pendientes" class="muted print-hide" hidden></p>
<p class="filters print-hide">
    <label><?= _("Año") ?>
        <select id="sel-anio-prevision" disabled></select>
    </label>
    <button type="button" id="btn-imprimir"><?= _("Imprimir") ?></button>
</p>
<p class="prevision-print-cab" id="print-cab-resumen"></p>
<div id="hoja-e37" class="informe-e37-wrap" hidden>
    <div class="informe-e37-scroll">
        <table id="tabla-prevision-e37" class="informe-e37-tabla informe-e37-resumen-prevision">
            <thead></thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<p id="aviso-saldo-cc" class="aviso-saldo-cc" hidden></p>
<script>
const I18N_PREV_RES = {
  cabecera: <?= json_encode(_("Previsión resumen E37"), JSON_UNESCAPED_UNICODE) ?>,
  errorCarga: <?= json_encode(_("No se pudo cargar el resumen"), JSON_UNESCAPED_UNICODE) ?>,
  pendientes: <?= json_encode(_("Falta guardar la previsión de:"), JSON_UNESCAPED_UNICODE) ?>,
  persona: <?= json_encode(_("Persona"), JSON_UNESCAPED_UNICODE) ?>,
};
const CENTRO_PREV_RES = <?= json_encode((string) ($centroNombre ?? ''), JSON_UNESCAPED_UNICODE) ?>;

let sincAniosPrevision = false;
let columnasE37 = [];

function fmtHoja(valorEs) {
  if (!valorEs || valorEs === '0,00' || valorEs === '-0,00') return '';
  return valorEs.endsWith(',00') ? valorEs.slice(0, -3) : valorEs;
}

function celdaNum(valorEs, extraClass) {
  const t = fmtHoja(valorEs);
  const cls = 'num' + (extraClass ? ' ' + extraClass : '');
  return `<td class="${cls}">${t ? esc(t) : ''}</td>`;
}

function etiquetaPrevisionSeleccionada() {
  const sel = document.getElementById('sel-anio-prevision');
  if (!sel || sel.disabled || !sel.value) return '';
  return sel.value;
}

function urlPrevisionResumenE37() {
  let url = '/api/previsiones/resumen-e37';
  const etiqueta = etiquetaPrevisionSeleccionada();
  if (etiqueta) url += '?etiqueta=' + encodeURIComponent(etiqueta);
  return url;
}

function listaEtiquetas(r) {
  const etiquetas = [];
  const push = (valor) => {
    const et = String(valor || '').trim();
    if (et && !etiquetas.includes(et)) etiquetas.push(et);
  };
  const raw = (r && (r.etiquetas || r.anios_disponibles)) || [];
  (Array.isArray(raw) ? raw : Object.values(raw)).forEach(push);
  if (r) {
    push(r.etiqueta_trabajo);
    push(r.etiqueta_defecto);
    push(r.etiqueta_presupuesto);
  }
  return etiquetas;
}

function pintarEtiquetasPrevision(r, forzarDefecto) {
  const sel = document.getElementById('sel-anio-prevision');
  if (!sel) return;
  const prev = sel.value;
  const etiquetas = listaEtiquetas(r);
  [...sel.options].forEach((o) => {
    if (o.value && !etiquetas.includes(o.value)) etiquetas.push(o.value);
  });
  const defecto = r && (r.etiqueta_defecto || r.etiqueta_presupuesto || r.anio_presupuesto);
  sincAniosPrevision = true;
  sel.innerHTML = '';
  etiquetas.forEach((et) => {
    const o = document.createElement('option');
    o.value = et;
    o.textContent = et;
    sel.appendChild(o);
  });
  if (forzarDefecto && defecto && etiquetas.includes(String(defecto))) sel.value = String(defecto);
  else if (prev && etiquetas.includes(prev)) sel.value = prev;
  else if (defecto && etiquetas.includes(String(defecto))) sel.value = String(defecto);
  else if (etiquetas.length) sel.value = etiquetas[etiquetas.length - 1];
  sel.disabled = etiquetas.length === 0;
  sincAniosPrevision = false;
}

async function cargarOpcionesPrevision() {
  const r = await api('/api/previsiones/personal/opciones');
  if (!r.ok) return;
  pintarEtiquetasPrevision(r, true);
}

function cabeceraResumen(anio) {
  return [I18N_PREV_RES.cabecera, CENTRO_PREV_RES, anio ? String(anio) : ''].filter(Boolean).join(' — ');
}

function pintarCabeceraTabla(cols) {
  const thead = document.querySelector('#tabla-prevision-e37 thead');
  const trCod = document.createElement('tr');
  trCod.className = 'informe-e37-codigos';
  trCod.innerHTML = '<th colspan="2"></th>' + cols.map((c) =>
    `<th class="num">${esc(c.codigo || '')}</th>`
  ).join('');
  const trEt = document.createElement('tr');
  trEt.className = 'informe-e37-etiquetas';
  trEt.innerHTML = `<th colspan="2">${esc(I18N_PREV_RES.persona)}</th>` + cols.map((c) =>
    `<th class="num">${esc(c.etiqueta)}</th>`
  ).join('');
  thead.innerHTML = '';
  thead.appendChild(trCod);
  thead.appendChild(trEt);
}

function pintarResumen(r) {
  document.getElementById('print-cab-resumen').textContent =
    cabeceraResumen(r.anio_presupuesto);
  columnasE37 = r.columnas || [];
  if (!columnasE37.length) {
    document.getElementById('hoja-e37').hidden = true;
    return alert(I18N_PREV_RES.errorCarga);
  }
  pintarCabeceraTabla(columnasE37);
  const tb = document.querySelector('#tabla-prevision-e37 tbody');
  tb.innerHTML = '';
  const avisoCc = r.aviso_saldo_cc;
  (r.filas || []).forEach((f) => {
    const tr = document.createElement('tr');
    const celdas = columnasE37.map((c) => {
      const es = f[c.clave + '_es'];
      if (c.clave === 'saldo_cc' && avisoCc && String(es || '').startsWith('-')) {
        return celdaNum(es, 'saldo-cc-neg');
      }
      return celdaNum(es);
    }).join('');
    tr.innerHTML = `<td colspan="2">${esc(f.nombre)}</td>${celdas}`;
    tb.appendChild(tr);
  });
  document.getElementById('hoja-e37').hidden = false;
  const pend = document.getElementById('msg-pendientes');
  if ((r.pendientes || []).length) {
    pend.hidden = false;
    pend.textContent = I18N_PREV_RES.pendientes + ' ' + r.pendientes.join(', ');
  } else {
    pend.hidden = true;
    pend.textContent = '';
  }
  const aviso = document.getElementById('aviso-saldo-cc');
  if (avisoCc) {
    aviso.hidden = false;
    aviso.textContent = avisoCc;
  } else {
    aviso.hidden = true;
    aviso.textContent = '';
  }
  pintarEtiquetasPrevision(r, false);
}

async function cargarResumen() {
  const r = await api(urlPrevisionResumenE37());
  if (!r.ok) return alert(r.error || I18N_PREV_RES.errorCarga);
  pintarResumen(r);
}

document.addEventListener('DOMContentLoaded', async () => {
  document.body.classList.add('e37-hoja', 'prevision-hoja');
  await cargarOpcionesPrevision();
  await cargarResumen();

  document.getElementById('sel-anio-prevision').addEventListener('change', () => {
    if (sincAniosPrevision) return;
    cargarResumen();
  });

  document.getElementById('btn-imprimir').addEventListener('click', () => window.print());
});
</script>
