<div class="print-hide">
    <h1><?= _("Donativos a Fundación") ?></h1>
    <p class="muted"><?= _("Por destino (41 en adelante): lo aportado por cada persona desde el inicio del ejercicio hasta la fecha de cierre.") ?></p>
    <form id="form-fundacion" class="grid-form">
        <label><?= _("Fundación") ?>
            <select name="concepto" id="sel-fundacion" required></select>
        </label>
    </form>
</div>
<h2 id="titulo-listado" class="listado-donativos-cab" hidden></h2>
<table id="tabla-donativos" hidden>
    <thead>
    <tr><th><?= _("Persona") ?></th><th class="num"><?= _("Acumulado") ?></th></tr>
    </thead>
    <tbody></tbody>
    <tfoot id="pie-donativos" hidden>
    <tr><th scope="row"><?= _("Total") ?></th><td class="num" id="celda-total"></td></tr>
    </tfoot>
</table>
<p class="muted" id="sin-datos" hidden><?= _("Nadie ha aportado a este destino en el periodo.") ?></p>
<script>
const I18N_DON_FUND = {
  periodo: <?= json_encode(_("%s — del %s al %s"), JSON_UNESCAPED_UNICODE) ?>,
};
function pintarTitulo(r) {
  const el = document.getElementById('titulo-listado');
  if (!r.destino || !r.periodo) {
    el.hidden = true;
    el.textContent = '';
    return;
  }
  el.textContent = I18N_DON_FUND.periodo
    .replace('%s', r.destino.etiqueta)
    .replace('%s', fmtFecha(r.periodo.desde))
    .replace('%s', fmtFecha(r.periodo.hasta));
  el.hidden = false;
}
async function cargarFundaciones() {
  const r = await api('/api/donativos-fundacion-sg');
  if (!r.ok) return alert(r.error || 'Error');
  const sel = document.getElementById('sel-fundacion');
  sel.innerHTML = '';
  const fundaciones = r.fundaciones || [];
  if (fundaciones.length === 0) {
    const o = document.createElement('option');
    o.value = '';
    o.textContent = <?= json_encode(_("No hay destinos configurados"), JSON_UNESCAPED_UNICODE) ?>;
    o.disabled = true;
    sel.appendChild(o);
    return;
  }
  fundaciones.forEach((f, i) => {
    const o = document.createElement('option');
    o.value = f.codigo;
    o.textContent = f.etiqueta;
    if (i === 0) o.selected = true;
    sel.appendChild(o);
  });
  await cargarFilas(sel.value);
}
async function cargarFilas(codigo) {
  const tabla = document.getElementById('tabla-donativos');
  const sin = document.getElementById('sin-datos');
  const pie = document.getElementById('pie-donativos');
  tabla.hidden = true;
  sin.hidden = true;
  pie.hidden = true;
  document.getElementById('titulo-listado').hidden = true;
  if (!codigo) return;
  const r = await api('/api/donativos-fundacion-sg?concepto=' + encodeURIComponent(codigo));
  if (!r.ok) return alert(r.error || 'Error');
  pintarTitulo(r);
  const filas = r.filas || [];
  const tb = document.querySelector('#tabla-donativos tbody');
  tb.innerHTML = '';
  if (filas.length === 0) {
    sin.hidden = false;
    return;
  }
  filas.forEach((f) => {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td>' + esc(f.nombre) + '</td><td class="num">' + esc(f.acumulado_es || '') + '</td>';
    tb.appendChild(tr);
  });
  document.getElementById('celda-total').textContent = r.total_es || '';
  pie.hidden = false;
  tabla.hidden = false;
}
document.addEventListener('DOMContentLoaded', () => {
  cargarFundaciones();
  document.getElementById('sel-fundacion').addEventListener('change', (ev) => {
    cargarFilas(ev.target.value);
  });
});
</script>
