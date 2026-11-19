<div class="print-hide">
    <h1><?= _("Aportaciones por destino") ?></h1>
    <p class="muted"><?= _("Por destino (41 en adelante): lo aportado por cada persona desde el inicio del ejercicio hasta la fecha de cierre. «Ingresos en caja» muestra los ingresos de cada persona que no han salido a necesidades generales ni a fundaciones (lo enviado ya sale en el listado de cada destino).") ?></p>
    <form id="form-fundacion" class="grid-form">
        <label><?= _("Destino") ?>
            <select name="concepto" id="sel-fundacion" required></select>
        </label>
    </form>
</div>
<h2 id="titulo-listado" class="listado-donativos-cab" hidden></h2>
<table id="tabla-donativos" hidden>
    <thead>
    <tr><th><?= _("Persona") ?></th><th class="num" id="th-acumulado"><?= _("Acumulado") ?></th></tr>
    </thead>
    <tbody></tbody>
    <tfoot id="pie-donativos" hidden>
    <tr><th scope="row"><?= _("Total") ?></th><td class="num" id="celda-total"></td></tr>
    </tfoot>
</table>
<p class="muted" id="sin-datos" hidden></p>
<script>
const I18N_DON_FUND = {
  periodo: <?= json_encode(_("%s — del %s al %s"), JSON_UNESCAPED_UNICODE) ?>,
  sinDatos: <?= json_encode(_("Nadie ha aportado a este destino en el periodo."), JSON_UNESCAPED_UNICODE) ?>,
  sinIngresosCaja: <?= json_encode(_("No hay ingresos en el periodo."), JSON_UNESCAPED_UNICODE) ?>,
  acumulado: <?= json_encode(_("Acumulado"), JSON_UNESCAPED_UNICODE) ?>,
  ingresos: <?= json_encode(_("Ingresos"), JSON_UNESCAPED_UNICODE) ?>,
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
  sel.appendChild(document.createElement('option'));
  fundaciones.forEach((f) => {
    const o = document.createElement('option');
    o.value = f.codigo;
    o.textContent = f.etiqueta;
    sel.appendChild(o);
  });
  sel.value = '';
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
  document.getElementById('th-acumulado').textContent = codigo === 'caja' ? I18N_DON_FUND.ingresos : I18N_DON_FUND.acumulado;
  const filas = r.filas || [];
  const tb = document.querySelector('#tabla-donativos tbody');
  tb.innerHTML = '';
  if (filas.length === 0) {
    sin.textContent = codigo === 'caja' ? I18N_DON_FUND.sinIngresosCaja : I18N_DON_FUND.sinDatos;
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
