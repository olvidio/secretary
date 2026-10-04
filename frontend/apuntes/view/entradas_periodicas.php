<h1><?= _("Entradas periódicas") ?></h1>
<p class="muted"><?= _("Como una entrada normal del talonario (contra caja), con periodicidad mensual, trimestral o anual. La fecha de referencia fija el día del mes (y el mes inicial en trimestral y anual). Las plantillas de Cuentas → Plantillas aparecen al inicio del desplegable de concepto.") ?></p>

<form id="form-entrada" class="grid-form">
    <input type="hidden" name="id" id="inp-id">
    <label><?= _("Iniciales") ?>
        <select name="iniciales" id="sel-iniciales"><option value=""></option></select>
    </label>
    <label><?= _("Concepto") ?>
        <select name="concepto_codigo" id="sel-concepto"><option value=""></option></select>
    </label>
    <label><?= _("Observaciones") ?>
        <input name="observaciones" id="inp-obs" autocomplete="off">
    </label>
    <label><?= _("Cantidad") ?>
        <input name="cantidad" id="inp-cant" required inputmode="decimal">
    </label>
    <label><?= _("Periodicidad") ?>
        <select name="periodicidad" id="sel-periodicidad" required>
            <option value="mensual"><?= _("Mensual") ?></option>
            <option value="trimestral"><?= _("Trimestral") ?></option>
            <option value="anual"><?= _("Anual") ?></option>
        </select>
    </label>
    <label><?= _("Fecha de referencia") ?>
        <input name="fecha_ancla" id="inp-fecha" type="date" required
               title="<?= htmlspecialchars(_("Día del mes en que toca; en trimestral/anual también el mes de partida."), ENT_QUOTES) ?>">
    </label>
    <button type="submit" id="btn-guardar"><?= _("Guardar") ?></button>
    <button type="button" id="btn-nuevo" hidden><?= _("Nueva") ?></button>
</form>

<table id="tabla-entradas">
    <thead>
    <tr>
        <th><?= _("Iniciales") ?></th>
        <th><?= _("Concepto") ?></th>
        <th><?= _("Observaciones") ?></th>
        <th class="num"><?= _("Cantidad") ?></th>
        <th><?= _("Periodicidad") ?></th>
        <th><?= _("Fecha ref.") ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<p class="muted" id="vacio" hidden><?= _("No hay entradas periódicas definidas.") ?></p>

<script>
const I18N = {
  editar: <?= json_encode(_("Editar"), JSON_UNESCAPED_UNICODE) ?>,
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  confirmBorrar: <?= json_encode(_("¿Borrar esta entrada periódica?"), JSON_UNESCAPED_UNICODE) ?>,
  guardar: <?= json_encode(_("Guardar"), JSON_UNESCAPED_UNICODE) ?>,
  guardarCambios: <?= json_encode(_("Guardar cambios"), JSON_UNESCAPED_UNICODE) ?>,
  nueva: <?= json_encode(_("Nueva"), JSON_UNESCAPED_UNICODE) ?>,
  plantillas: <?= json_encode(_("Plantillas"), JSON_UNESCAPED_UNICODE) ?>,
  conceptos: <?= json_encode(_("Conceptos"), JSON_UNESCAPED_UNICODE) ?>,
};
const mapConcepto = {};

function etiquetaConcepto(codigo) {
  return mapConcepto[codigo] || codigo;
}

function resetForm() {
  document.getElementById('form-entrada').reset();
  document.getElementById('inp-id').value = '';
  document.getElementById('btn-guardar').textContent = I18N.guardar;
  document.getElementById('btn-nuevo').hidden = true;
  const hoy = new Date().toISOString().slice(0, 10);
  document.getElementById('inp-fecha').value = hoy;
}

async function cargarTabla() {
  const r = await api('/api/entradas-periodicas');
  if (!r.ok) return alert(r.error || 'Error');
  const tb = document.querySelector('#tabla-entradas tbody');
  tb.innerHTML = '';
  const filas = r.entradas || [];
  document.getElementById('vacio').hidden = filas.length > 0;
  filas.forEach(e => {
    const tr = document.createElement('tr');
    const etiq = etiquetaConcepto(e.concepto_codigo);
    tr.innerHTML =
      '<td>' + esc(e.iniciales) + '</td>'
      + '<td>' + esc(etiq) + '</td>'
      + '<td>' + esc(e.observaciones || '') + '</td>'
      + '<td class="num">' + esc(e.cantidad) + '</td>'
      + '<td>' + esc(e.periodicidad_etiqueta || e.periodicidad) + '</td>'
      + '<td>' + esc(fmtFecha(e.fecha_ancla)) + '</td>'
      + '<td><button type="button" class="btn-edit">' + esc(I18N.editar) + '</button> '
      + '<button type="button" class="btn-del peligro">' + esc(I18N.borrar) + '</button></td>';
    tr.querySelector('.btn-edit').onclick = () => {
      document.getElementById('inp-id').value = e.id;
      document.getElementById('sel-iniciales').value = e.iniciales;
      document.getElementById('sel-concepto').value = e.concepto_codigo;
      document.getElementById('inp-obs').value = e.observaciones || '';
      document.getElementById('inp-cant').value = e.cantidad.replace('.', ',');
      document.getElementById('sel-periodicidad').value = e.periodicidad;
      document.getElementById('inp-fecha').value = e.fecha_ancla;
      document.getElementById('btn-guardar').textContent = I18N.guardarCambios;
      document.getElementById('btn-nuevo').hidden = false;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    tr.querySelector('.btn-del').onclick = async () => {
      if (!confirm(I18N.confirmBorrar)) return;
      const s = await api('/api/entradas-periodicas/' + e.id, { method: 'DELETE' });
      if (!s.ok) return alert(s.error || 'Error');
      await cargarTabla();
    };
    tb.appendChild(tr);
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  resetForm();
  const pers = await api('/api/personas');
  const selI = document.getElementById('sel-iniciales');
  selI.innerHTML = '<option value=""></option>';
  (pers.personas || []).forEach(p => {
    const o = document.createElement('option');
    o.value = p.iniciales;
    o.textContent = p.iniciales + ' — ' + p.nombre_completo;
    selI.appendChild(o);
  });
  const selC = document.getElementById('sel-concepto');
  selC.innerHTML = '<option value=""></option>';
  const ogPlant = document.createElement('optgroup');
  ogPlant.label = I18N.plantillas;
  selC.appendChild(ogPlant);
  const rPlant = await api('/api/plantillas-apunte?cuenta=G');
  if (!rPlant.ok) {
    alert(rPlant.error || 'Error');
    return;
  }
  (rPlant.plantillas || []).forEach(p => {
    const codigo = '@plantilla:' + p.id;
    mapConcepto[codigo] = p.etiqueta || p.nombre;
    const o = document.createElement('option');
    o.value = codigo;
    o.textContent = p.etiqueta || p.nombre;
    ogPlant.appendChild(o);
  });
  ogPlant.hidden = ogPlant.children.length === 0;
  const ogCons = document.createElement('optgroup');
  ogCons.label = I18N.conceptos;
  selC.appendChild(ogCons);
  const cons = await api('/api/conceptos?cuenta=G');
  if (!cons.ok) {
    alert(cons.error || 'Error');
    return;
  }
  (cons.conceptos || []).forEach(c => {
    mapConcepto[c.codigo] = c.etiqueta;
    const o = document.createElement('option');
    o.value = c.codigo;
    o.textContent = c.etiqueta;
    ogCons.appendChild(o);
  });

  document.getElementById('btn-nuevo').onclick = () => resetForm();
  document.getElementById('form-entrada').onsubmit = async (ev) => {
    ev.preventDefault();
    const body = formObj(document.getElementById('form-entrada'));
    const s = await api('/api/entradas-periodicas', { method: 'POST', body });
    if (!s.ok) return alert(s.error || 'Error');
    resetForm();
    await cargarTabla();
  };

  await cargarTabla();
});
</script>
