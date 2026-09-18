<h1><?= _("Configuración") ?></h1>
<form id="form-config" class="grid-form">
    <label><?= _("Centro") ?> <input name="centro" required></label>
    <label><?= _("Año") ?> <input name="anio" type="number" required></label>
    <label><?= _("Ejercicio") ?>
        <select name="modo_ejercicio">
            <option><?= _("Año") ?></option>
            <option><?= _("Curso") ?></option>
        </select>
    </label>
    <label><?= _("Fecha inicio") ?> <input name="fecha_inicio" type="date" required></label>
    <label><?= _("Fecha cierre") ?> <input name="fecha_cierre" type="date" required></label>
    <label><?= _("Tipo de centro") ?>
        <select name="tipo">
            <option value="n"><?= _("n") ?></option>
            <option value="sg"><?= _("sg") ?></option>
        </select>
    </label>
    <label><?= _("Tipo de cierre") ?>
        <select name="tipo_cierre">
            <option value="vivienda"><?= _("Vivienda — P 21 / G 11") ?></option>
            <option value="necesidades"><?= _("Necesidades — P 6 / G 14") ?></option>
        </select>
    </label>
    <label><?= _("Plan contable") ?>
        <select name="plan_contable" required></select>
    </label>
    <button type="submit"><?= _("Guardar") ?></button>
    <p class="ok" id="msg" hidden><?= _("Guardado") ?></p>
</form>
<section>
    <h2><?= _("Tramos de desgravación") ?></h2>
    <p class="muted"><?= _("Se usan al proponer destinos 7. El primer tramo (p. ej. 250 € al 80 %) se reparte entre varias personas antes de subir el importe de una sola.") ?></p>
    <table id="tabla-tramos">
        <thead><tr><th><?= _("Hasta (€, vacío = resto)") ?></th><th>%</th><th></th></tr></thead>
        <tbody></tbody>
    </table>
    <p>
        <button type="button" id="btn-add-tramo"><?= _("Añadir tramo") ?></button>
        <button type="button" id="btn-save-tramos"><?= _("Guardar tramos") ?></button>
    </p>
    <p class="ok" id="msg-tramos" hidden><?= _("Tramos guardados") ?></p>
</section>
<script>
const I18N_CONFIG = {
  quitar: <?= json_encode(_("Quitar"), JSON_UNESCAPED_UNICODE) ?>,
};
function filaTramo(t = {}) {
  const tr = document.createElement('tr');
  const hasta = t.hasta_cents == null ? '' : (Number(t.hasta_cents) / 100).toFixed(2);
  tr.innerHTML = '<td><input name="hasta" inputmode="decimal" value="' + esc(hasta) + '"></td>'
    + '<td><input name="pct" type="number" min="0" max="100" required value="' + esc(String(t.porcentaje ?? '')) + '"></td>'
    + '<td><button type="button" class="btn-quitar">' + esc(I18N_CONFIG.quitar) + '</button></td>';
  tr.querySelector('.btn-quitar').onclick = () => tr.remove();
  return tr;
}
async function loadTramos() {
  const r = await api('/api/desgravacion-tramos');
  const tb = document.querySelector('#tabla-tramos tbody');
  tb.innerHTML = '';
  (r.tramos || []).forEach((t) => tb.appendChild(filaTramo(t)));
}
function rellenarPlanes(select, planes, seleccionado) {
  select.innerHTML = '';
  (planes || []).forEach((p) => {
    const opt = document.createElement('option');
    opt.value = p.codigo;
    opt.textContent = p.nombre || p.codigo;
    select.appendChild(opt);
  });
  if (seleccionado) select.value = seleccionado;
}
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/configuracion');
  const planSelect = document.querySelector('#form-config [name=plan_contable]');
  rellenarPlanes(planSelect, r.planes, r.config?.plan_contable);
  fillForm(document.getElementById('form-config'), r.config);
  document.getElementById('form-config').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const body = formObj(ev.target);
    const s = await api('/api/configuracion', {method:'POST', body});
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) alert(s.error);
  });
  loadTramos();
  document.getElementById('btn-add-tramo').onclick = () => {
    document.querySelector('#tabla-tramos tbody').appendChild(filaTramo({ porcentaje: 40 }));
  };
  document.getElementById('btn-save-tramos').onclick = async () => {
    const tramos = [...document.querySelectorAll('#tabla-tramos tbody tr')].map((tr) => {
      const hasta = tr.querySelector('[name=hasta]').value.trim();
      const pct = Number(tr.querySelector('[name=pct]').value);
      let hastaCents = null;
      if (hasta !== '') {
        const n = Number(hasta.replace(',', '.'));
        hastaCents = Math.round(n * 100);
      }
      return { hasta_cents: hastaCents, porcentaje: pct };
    });
    const s = await api('/api/desgravacion-tramos', { method: 'POST', body: { tramos } });
    document.getElementById('msg-tramos').hidden = !s.ok;
    if (!s.ok) alert(s.error);
  };
});
</script>
