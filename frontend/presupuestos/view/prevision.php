<style>
@page { size: A4 landscape; margin: 8mm; }
</style>
<h1 class="print-hide"><?= _("Previsión") ?></h1>
<p class="muted print-hide"><?= _("Misma hoja 613 P: la primera columna es la suma y cada nombre lleva el importe guardado en su previsión personal.") ?></p>
<p id="msg-pendientes" class="muted print-hide" hidden></p>
<p class="filters print-hide">
    <button type="button" id="btn-imprimir"><?= _("Imprimir") ?></button>
    <button type="button" id="btn-aplicar-presupuesto"><?= _("Aplicar al presupuesto P") ?></button>
</p>
<p id="msg-prevision" class="ok print-hide" hidden><?= _("Guardado en presupuesto P") ?></p>
<p class="prevision-print-cab"><?= _("Previsión 613 P") ?><?php if (!empty($centroNombre)): ?> — <?= htmlspecialchars((string) $centroNombre, ENT_QUOTES) ?><?php endif; ?></p>
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
  errorCarga: <?= json_encode(_("No se pudo cargar la previsión"), JSON_UNESCAPED_UNICODE) ?>,
  pendientes: <?= json_encode(_("Falta guardar la previsión de:"), JSON_UNESCAPED_UNICODE) ?>,
  confirmar: <?= json_encode(_("¿Copiar la columna Total al presupuesto P? Se actualizan las líneas del 613 P."), JSON_UNESCAPED_UNICODE) ?>,
};

function fmtPrev(valorEs) {
  if (!valorEs || valorEs === '0,00' || valorEs === '-0,00') return '';
  return valorEs.endsWith(',00') ? valorEs.slice(0, -3) : valorEs;
}

function pintarConsolidada(r) {
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
}

document.addEventListener('DOMContentLoaded', async () => {
  document.body.classList.add('prevision-hoja');
  const r = await api('/api/previsiones');
  if (!r.ok) return alert(r.error || I18N_PREV_C.errorCarga);
  pintarConsolidada(r);

  document.getElementById('btn-imprimir').addEventListener('click', () => window.print());

  document.getElementById('btn-aplicar-presupuesto').addEventListener('click', async () => {
    if (!confirm(I18N_PREV_C.confirmar)) return;
    const s = await api('/api/previsiones/aplicar-presupuesto', { method: 'POST', body: {} });
    document.getElementById('msg-prevision').hidden = !s.ok;
    if (!s.ok) return alert(s.error);
    pintarConsolidada(s);
  });
});
</script>
