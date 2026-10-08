<style>
@page { size: A4 landscape; margin: 8mm; }
</style>
<h1 class="print-hide"><?= _("Previsión") ?></h1>
<p class="muted print-hide"><?= _("Misma hoja 613 P: la primera columna es la suma y cada nombre lleva el importe guardado en su previsión personal.") ?></p>
<p id="msg-pendientes" class="muted print-hide" hidden></p>
<p class="filters print-hide">
    <label><?= _("Año") ?>
        <select id="sel-anio-prevision" disabled></select>
    </label>
    <button type="button" id="btn-imprimir"><?= _("Imprimir") ?></button>
    <button type="button" id="btn-aplicar-presupuesto"><?= _("Aplicar al presupuesto P") ?></button>
</p>
<p id="msg-prevision" class="ok print-hide" hidden><?= _("Guardado en presupuesto P") ?></p>
<p class="prevision-print-cab" id="print-cab-prevision"></p>
<div class="tabla-scroll informe-prevision-wrap">
<table id="tabla-prevision" class="tabla-prevision" hidden>
    <thead></thead>
    <tbody></tbody>
</table>
</div>
<script>
const I18N_PREV_C = {
  total: <?= json_encode(_("Total"), JSON_UNESCAPED_UNICODE) ?>,
  concepto: <?= json_encode(_("Concepto"), JSON_UNESCAPED_UNICODE) ?>,
  cabecera: <?= json_encode(_("Previsión 613 P"), JSON_UNESCAPED_UNICODE) ?>,
  errorCarga: <?= json_encode(_("No se pudo cargar la previsión"), JSON_UNESCAPED_UNICODE) ?>,
  pendientes: <?= json_encode(_("Falta guardar la previsión de:"), JSON_UNESCAPED_UNICODE) ?>,
  confirmar: <?= json_encode(_("¿Copiar la columna Total al presupuesto P? Se actualizan las líneas del 613 P."), JSON_UNESCAPED_UNICODE) ?>,
};
const CENTRO_PREV_C = <?= json_encode((string) ($centroNombre ?? ''), JSON_UNESCAPED_UNICODE) ?>;

let sincAniosPrevision = false;

function etiquetaPrevisionSeleccionada() {
  const sel = document.getElementById('sel-anio-prevision');
  if (!sel || sel.disabled || !sel.value) return '';
  return sel.value;
}

function urlPrevisionConsolidada() {
  let url = '/api/previsiones';
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

function cabeceraPrevision(anio) {
  return [I18N_PREV_C.cabecera, CENTRO_PREV_C, anio ? String(anio) : ''].filter(Boolean).join(' — ');
}

function fmtPrev(valorEs) {
  return fmtEnteroEs(valorEs);
}

function pintarConsolidada(r) {
  document.getElementById('print-cab-prevision').textContent =
    cabeceraPrevision(r.anio_presupuesto);
  const tabla = document.getElementById('tabla-prevision');
  const thead = tabla.querySelector('thead');
  const tbody = tabla.querySelector('tbody');
  const personas = r.personas || [];
  thead.innerHTML = '<tr><th class="col-concepto">' + esc(I18N_PREV_C.concepto) + '</th>'
    + '<th class="num">' + esc(I18N_PREV_C.total) + '</th>'
    + personas.map((p) => '<th class="num" title="' + esc(p.nombre) + '">'
      + '<span class="nombre-largo">' + esc(p.nombre_corto || p.nombre) + '</span>'
      + '<span class="nombre-corto">' + esc(p.iniciales) + '</span>'
      + '</th>').join('')
    + '</tr>';
  tbody.innerHTML = '';
  const filas = r.filas && r.filas.length ? r.filas : (r.lineas || []).map((l) => Object.assign({ tipo: 'hijo' }, l));
  filas.forEach((l) => {
    const tr = document.createElement('tr');
    tr.className = 'prevision-' + (l.tipo || 'hijo');
    const etq = l.codigo && l.tipo === 'hijo'
      ? esc(l.codigo) + ' ' + esc(l.etiqueta)
      : esc(l.etiqueta);
    tr.innerHTML = '<td class="col-concepto">' + etq + '</td>'
      + '<td class="num">' + esc(fmtPrev(l.total_es)) + '</td>'
      + (l.personas || []).map((c) => '<td class="num">' + esc(fmtPrev(c.previsto_es)) + '</td>').join('');
    tbody.appendChild(tr);
  });
  tabla.hidden = false;
  const pend = document.getElementById('msg-pendientes');
  if ((r.pendientes || []).length) {
    pend.hidden = false;
    pend.textContent = I18N_PREV_C.pendientes + ' ' + r.pendientes.join(', ');
  } else {
    pend.hidden = true;
    pend.textContent = '';
  }
  pintarEtiquetasPrevision(r, false);
}

async function cargarConsolidada() {
  const r = await api(urlPrevisionConsolidada());
  if (!r.ok) return alert(r.error || I18N_PREV_C.errorCarga);
  pintarConsolidada(r);
}

document.addEventListener('DOMContentLoaded', async () => {
  document.body.classList.add('prevision-hoja');
  await cargarOpcionesPrevision();
  await cargarConsolidada();

  document.getElementById('sel-anio-prevision').addEventListener('change', () => {
    if (sincAniosPrevision) return;
    cargarConsolidada();
  });

  document.getElementById('btn-imprimir').addEventListener('click', () => window.print());

  document.getElementById('btn-aplicar-presupuesto').addEventListener('click', async () => {
    if (!confirm(I18N_PREV_C.confirmar)) return;
    const body = {};
    const etiqueta = etiquetaPrevisionSeleccionada();
    if (etiqueta) body.etiqueta = etiqueta;
    const s = await api('/api/previsiones/aplicar-presupuesto', { method: 'POST', body });
    document.getElementById('msg-prevision').hidden = !s.ok;
    if (!s.ok) return alert(s.error);
    pintarConsolidada(s);
  });
});
</script>
