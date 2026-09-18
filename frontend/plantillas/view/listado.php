<?php $cuenta = $cuentaEntrada ?? 'P'; ?>
<h1><?= sprintf(_("Plantillas de apuntes %s"), htmlspecialchars($cuenta, ENT_QUOTES)) ?></h1>
<p class="muted"><?= sprintf(_("Solo define los movimientos (origen, concepto, observaciones). Las plantillas de este listado aparecen al final del desplegable de concepto en Entrada %s (no en el otro libro). Al usarlas, las iniciales, la fecha y la cantidad salen de la cabecera del formulario."), htmlspecialchars($cuenta, ENT_QUOTES)) ?></p>

<form id="form-plantilla" class="grid-form">
    <input type="hidden" name="id">
    <input type="hidden" name="cuenta" value="<?= htmlspecialchars($cuenta, ENT_QUOTES) ?>">
    <label><?= _("Nombre") ?> <input name="nombre" required placeholder="<?= htmlspecialchars(_("p. ej. Club"), ENT_QUOTES) ?>"></label>
    <fieldset class="plantilla-lineas">
        <legend><?= _("Movimientos") ?></legend>
        <div id="lineas-form"></div>
        <button type="button" id="btn-add-linea"><?= _("Añadir movimiento") ?></button>
    </fieldset>
    <button type="submit" id="btn-guardar"><?= _("Guardar plantilla") ?></button>
    <button type="button" id="btn-nuevo" hidden><?= _("Nueva plantilla") ?></button>
</form>

<table id="tabla-plantillas">
    <thead>
    <tr><th><?= _("Nombre") ?></th><th><?= _("Movimientos") ?></th><th></th></tr>
    </thead>
    <tbody></tbody>
</table>

<script>
const CUENTA = <?= json_encode($cuenta) ?>;
const I18N_PLANTILLAS = {
  apunte: <?= json_encode(_("Apunte"), JSON_UNESCAPED_UNICODE) ?>,
  banco: <?= json_encode(_("Banco"), JSON_UNESCAPED_UNICODE) ?>,
  caja: <?= json_encode(_("Caja"), JSON_UNESCAPED_UNICODE) ?>,
  quitar: <?= json_encode(_("Quitar"), JSON_UNESCAPED_UNICODE) ?>,
  editar: <?= json_encode(_("Editar"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  confirmBorrar: <?= json_encode(_("¿Borrar plantilla «%s»?"), JSON_UNESCAPED_UNICODE) ?>,
  guardarPlantilla: <?= json_encode(_("Guardar plantilla"), JSON_UNESCAPED_UNICODE) ?>,
  guardarCambios: <?= json_encode(_("Guardar cambios"), JSON_UNESCAPED_UNICODE) ?>,
  elijaConcepto: <?= json_encode(_("— concepto —"), JSON_UNESCAPED_UNICODE) ?>,
  error: <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>,
};

const conceptosPorCuenta = { P: [], G: [] };

function origenOpts() {
  return '<option value="A">' + esc(I18N_PLANTILLAS.apunte) + '</option>'
    + '<option value="B">' + esc(I18N_PLANTILLAS.banco) + '</option>'
    + '<option value="C">' + esc(I18N_PLANTILLAS.caja) + '</option>';
}

function pintarSelectConcepto(sel, cuenta, codigoSeleccionado) {
  sel.innerHTML = '';
  const vacio = document.createElement('option');
  vacio.value = '';
  vacio.textContent = I18N_PLANTILLAS.elijaConcepto;
  sel.appendChild(vacio);
  (conceptosPorCuenta[cuenta] || []).forEach(c => {
    const o = document.createElement('option');
    o.value = c.codigo;
    o.textContent = c.etiqueta;
    sel.appendChild(o);
  });
  if (codigoSeleccionado && [...sel.options].some(o => o.value === codigoSeleccionado)) {
    sel.value = codigoSeleccionado;
  }
}

function filaLinea(d = {}) {
  const div = document.createElement('div');
  div.className = 'plantilla-linea grid-form';
  div.innerHTML =
    '<label>P/G <select name="linea_cuenta"><option>P</option><option>G</option></select></label>' +
    '<label>A/B/C <select name="origen" required>' + origenOpts() + '</select></label>' +
    '<label><?= _("Concepto") ?> <select name="concepto_codigo" required></select></label>' +
    '<label><?= _("Observaciones") ?> <input name="observaciones" value="' + esc(d.observaciones || '') + '"></label>' +
    '<button type="button" class="btn-quitar">' + esc(I18N_PLANTILLAS.quitar) + '</button>';
  const selCuenta = div.querySelector('[name=linea_cuenta]');
  const selConcepto = div.querySelector('[name=concepto_codigo]');
  selCuenta.value = d.cuenta || CUENTA;
  div.querySelector('[name=origen]').value = d.origen || 'A';
  pintarSelectConcepto(selConcepto, selCuenta.value, d.concepto_codigo || '');
  selCuenta.onchange = () => pintarSelectConcepto(selConcepto, selCuenta.value, '');
  div.querySelector('.btn-quitar').onclick = () => div.remove();
  return div;
}

function lineasDelForm() {
  return [...document.querySelectorAll('#lineas-form .plantilla-linea')].map(row => ({
    cuenta: row.querySelector('[name=linea_cuenta]').value,
    origen: row.querySelector('[name=origen]').value,
    concepto_codigo: row.querySelector('[name=concepto_codigo]').value,
    observaciones: row.querySelector('[name=observaciones]').value,
  }));
}

function resumenLineas(lineas) {
  return (lineas || []).map(l =>
    (l.cuenta || 'P') + ' ' + l.origen + ' ' + l.concepto_codigo
    + (l.observaciones ? ' · ' + l.observaciones : '')
  ).join(' → ');
}

function pintarLineas(lineas) {
  const cont = document.getElementById('lineas-form');
  cont.innerHTML = '';
  (lineas.length ? lineas : [{ cuenta: CUENTA }]).forEach(l => cont.appendChild(filaLinea(l)));
}

function resetFormulario() {
  const form = document.getElementById('form-plantilla');
  form.reset();
  form.querySelector('[name=cuenta]').value = CUENTA;
  form.querySelector('[name=id]').value = '';
  pintarLineas([]);
  document.getElementById('btn-guardar').textContent = I18N_PLANTILLAS.guardarPlantilla;
  document.getElementById('btn-nuevo').hidden = true;
}

function editarPlantilla(p) {
  const form = document.getElementById('form-plantilla');
  form.querySelector('[name=id]').value = p.id;
  form.querySelector('[name=nombre]').value = p.nombre;
  form.querySelector('[name=cuenta]').value = p.cuenta || CUENTA;
  pintarLineas(p.lineas || []);
  document.getElementById('btn-guardar').textContent = I18N_PLANTILLAS.guardarCambios;
  document.getElementById('btn-nuevo').hidden = false;
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function loadPlantillas() {
  const r = await api('/api/plantillas-apunte?cuenta=' + CUENTA);
  if (!r.ok) return alert(r.error);
  const tb = document.querySelector('#tabla-plantillas tbody');
  tb.innerHTML = '';
  (r.plantillas || []).forEach(p => {
    const tr = document.createElement('tr');
    tr.innerHTML =
      '<td>' + esc(p.nombre) + '</td>' +
      '<td class="muted">' + esc(resumenLineas(p.lineas)) + '</td>' +
      '<td><button type="button" data-edit="' + p.id + '">' + esc(I18N_PLANTILLAS.editar) + '</button> ' +
      '<button type="button" data-del="' + p.id + '">' + esc(I18N_PLANTILLAS.borrar) + '</button></td>';
    tr.querySelector('[data-edit]').onclick = () => editarPlantilla(p);
    tr.querySelector('[data-del]').onclick = async () => {
      if (!confirm(I18N_PLANTILLAS.confirmBorrar.replace('%s', p.nombre))) return;
      const s = await api('/api/plantillas-apunte/' + p.id, { method: 'DELETE' });
      if (!s.ok) return alert(s.error);
      resetFormulario();
      loadPlantillas();
    };
    tb.appendChild(tr);
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  const [rP, rG] = await Promise.all([
    api('/api/conceptos?cuenta=P'),
    api('/api/conceptos?cuenta=G'),
  ]);
  if (!rP.ok || !rG.ok) {
    alert((rP.error || rG.error) || I18N_PLANTILLAS.error);
    return;
  }
  conceptosPorCuenta.P = rP.conceptos || [];
  conceptosPorCuenta.G = rG.conceptos || [];

  resetFormulario();

  document.getElementById('btn-add-linea').onclick = () => {
    document.getElementById('lineas-form').appendChild(filaLinea({ cuenta: CUENTA }));
  };
  document.getElementById('btn-nuevo').onclick = () => resetFormulario();

  document.getElementById('form-plantilla').onsubmit = async (ev) => {
    ev.preventDefault();
    const body = formObj(ev.target);
    if (!body.id) delete body.id;
    body.cuenta = CUENTA;
    body.lineas = lineasDelForm();
    const s = await api('/api/plantillas-apunte', { method: 'POST', body });
    if (!s.ok) return alert(s.error);
    resetFormulario();
    loadPlantillas();
  };

  loadPlantillas();
});
</script>
