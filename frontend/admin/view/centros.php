<h1><?= _("Entidades") ?></h1>
<p class="muted"><?= _("Alta y baja de entidades contables. Cada una lleva su secretario y su plan de cuentas. Una persona solo puede vincularse a un centro n.") ?></p>
<table id="tabla-centros">
    <thead><tr><th><?= _("Sigla") ?></th><th><?= _("Nombre") ?></th><th><?= _("Tipo de entidad") ?></th><th><?= _("Plan") ?></th><th><?= _("Cierre") ?></th><th></th></tr></thead>
    <tbody></tbody>
</table>
<h2><?= _("Nueva entidad") ?></h2>
<form id="form-centro" class="grid-form">
    <label><?= _("Sigla") ?> <input name="codigo" required></label>
    <label><?= _("Nombre") ?> <input name="nombre" required></label>
    <label><?= _("Plan contable") ?> <select name="plan_contable" required></select></label>
    <label><?= _("Tipo de cierre") ?>
        <select name="tipo_cierre">
            <option value="vivienda"><?= _("Vivienda") ?></option>
            <option value="necesidades"><?= _("Necesidades") ?></option>
        </select>
    </label>
    <label><?= _("Tipo de entidad") ?>
        <select name="tipo">
            <option value="n"><?= _("centro n") ?></option>
            <option value="sg"><?= _("centro sg") ?></option>
            <option value="asociacion"><?= _("asociación") ?></option>
            <option value="fundacion"><?= _("fundación") ?></option>
        </select>
    </label>
    <label><?= _("Ejercicio desde") ?> <input name="fecha_inicio" type="date" required></label>
    <label><?= _("Ejercicio hasta") ?> <input name="fecha_fin" type="date" required></label>
    <label><?= _("Alias del secretario") ?> <input name="usuario" required placeholder="<?= htmlspecialchars(_("Persona, no sigla del centro"), ENT_QUOTES) ?>"></label>
    <label><?= _("Nombre del secretario") ?> <input name="nombre_usuario" autocomplete="name"></label>
    <label><?= _("Correo secretario") ?> <input name="email" type="email" required></label>
    <label><?= _("Contraseña") ?> <input name="password" type="password" required minlength="6"></label>
    <label><?= _("Excel (opcional)") ?> <input name="excel" type="file" accept=".xlsm,.xlsx"></label>
    <button type="submit"><?= _("Crear entidad") ?></button>
</form>
<p class="ok" id="msg-centro" hidden></p>
<script>
const I18N_ADMIN_CENTROS = {
  borrar: <?= json_encode(_("Borrar"), JSON_UNESCAPED_UNICODE) ?>,
  confirmBorrar: <?= json_encode(_("Esto borra la entidad y todos sus datos. ¿Seguro?"), JSON_UNESCAPED_UNICODE) ?>,
  creado: <?= json_encode(_("Entidad creada."), JSON_UNESCAPED_UNICODE) ?>,
  tipos: {
    n: <?= json_encode(_("centro n"), JSON_UNESCAPED_UNICODE) ?>,
    sg: <?= json_encode(_("centro sg"), JSON_UNESCAPED_UNICODE) ?>,
    asociacion: <?= json_encode(_("asociación"), JSON_UNESCAPED_UNICODE) ?>,
    fundacion: <?= json_encode(_("fundación"), JSON_UNESCAPED_UNICODE) ?>,
  },
};
function filaCentro(c) {
  const tr = document.createElement('tr');
  tr.innerHTML =
    '<td>' + esc(c.codigo) + '</td>' +
    '<td>' + esc(c.nombre) + '</td>' +
    '<td>' + esc(I18N_ADMIN_CENTROS.tipos[c.tipo] || c.tipo || '') + '</td>' +
    '<td>' + esc(c.plan_contable || '') + '</td>' +
    '<td>' + esc(c.tipo_cierre || '') + '</td>' +
    '<td><button type="button" class="btn-del peligro">' + esc(I18N_ADMIN_CENTROS.borrar) + '</button></td>';
  tr.querySelector('.btn-del').onclick = async () => {
    if (!confirm(I18N_ADMIN_CENTROS.confirmBorrar)) return;
    const s = await api('/api/admin/centros/' + c.id + '/borrar', { method: 'POST', body: { confirmar: true } });
    if (!s.ok) return alert(s.error);
    await loadCentros();
  };
  return tr;
}
function planPorTipo(tipo) {
  if (tipo === 'sg') return 'H16s';
  if (tipo === 'asociacion' || tipo === 'fundacion') return 'Club';
  return 'H16n';
}
function aplicarPlanPorTipo() {
  const tipo = document.querySelector('#form-centro [name=tipo]')?.value || 'n';
  const plan = document.querySelector('#form-centro [name=plan_contable]');
  const codigo = planPorTipo(tipo);
  if (plan && [...plan.options].some((o) => o.value === codigo)) plan.value = codigo;
}
function rellenarPlanes(select, planes) {
  select.innerHTML = '';
  (planes || []).forEach((p) => {
    const opt = document.createElement('option');
    opt.value = p.codigo;
    opt.textContent = p.nombre || p.codigo;
    select.appendChild(opt);
  });
}
async function loadCentros() {
  const r = await api('/api/admin/centros');
  if (!r.ok) return alert(r.error);
  rellenarPlanes(document.querySelector('#form-centro [name=plan_contable]'), r.planes);
  aplicarPlanPorTipo();
  const tb = document.querySelector('#tabla-centros tbody');
  tb.innerHTML = '';
  (r.centros || []).forEach((c) => tb.appendChild(filaCentro(c)));
}
document.addEventListener('DOMContentLoaded', () => {
  const year = new Date().getFullYear();
  const ini = document.querySelector('#form-centro [name=fecha_inicio]');
  const fin = document.querySelector('#form-centro [name=fecha_fin]');
  if (ini) ini.value = year + '-01-01';
  if (fin) fin.value = year + '-12-31';
  loadCentros();
  document.querySelector('#form-centro [name=tipo]')?.addEventListener('change', aplicarPlanPorTipo);
  document.getElementById('form-centro').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/admin/centros', { method: 'POST', body: new FormData(ev.target) });
    if (!s.ok) return alert(s.error);
    document.getElementById('msg-centro').hidden = false;
    document.getElementById('msg-centro').textContent = I18N_ADMIN_CENTROS.creado;
    ev.target.reset();
    if (ini) ini.value = year + '-01-01';
    if (fin) fin.value = year + '-12-31';
    await loadCentros();
  };
});
</script>
