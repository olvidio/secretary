<h1><?= _("Tesorería física") ?></h1>
<p><?= _("Cuentas reales del centro (caja o banco). Al dar de alta una física se crean automáticamente sus cuentas de mayor CAJA.n/P, CAJA.n/G o BANCO.n/P, BANCO.n/G.") ?></p>

<form id="form-fisica" class="grid-form">
    <label><?= _("Tipo") ?>
        <select name="tipo" required>
            <option value="caja"><?= _("Caja") ?></option>
            <option value="banco"><?= _("Banco") ?></option>
        </select>
    </label>
    <label><?= _("Nombre") ?> <input name="nombre" required placeholder="<?= htmlspecialchars(_("p. ej. Banc Sabadell"), ENT_QUOTES) ?>"></label>
    <label><?= _("IBAN (opcional)") ?> <input name="iban" placeholder="ES…"></label>
    <button type="submit"><?= _("Alta") ?></button>
</form>

<table id="tabla-fisicas">
    <thead>
    <tr><th><?= _("Tipo") ?></th><th><?= _("Nombre") ?></th><th>IBAN</th><th><?= _("Orden") ?></th><th><?= _("Activa") ?></th><th></th></tr>
    </thead>
    <tbody></tbody>
</table>
<script>
const I18N_TESORERIA = {
  si: <?= json_encode(_("Sí"), JSON_UNESCAPED_UNICODE) ?>,
  no: <?= json_encode(_("No"), JSON_UNESCAPED_UNICODE) ?>,
  desactivar: <?= json_encode(_("Desactivar"), JSON_UNESCAPED_UNICODE) ?>,
  confirmDesact: <?= json_encode(_("¿Desactivar esta cuenta física?"), JSON_UNESCAPED_UNICODE) ?>,
};
async function loadFisicas() {
  const r = await api('/api/tesoreria');
  const tb = document.querySelector('#tabla-fisicas tbody');
  tb.innerHTML = '';
  (r.cuentas_fisicas || []).forEach(f => {
    const tr = document.createElement('tr');
    const btn = f.activo
      ? `<button type="button" data-id="${f.id}" class="btn-desact">${esc(I18N_TESORERIA.desactivar)}</button>`
      : '';
    tr.innerHTML = `<td>${esc(f.tipo)}</td><td>${esc(f.nombre)}</td><td>${esc(f.iban || '')}</td>
      <td>${f.orden}</td><td>${f.activo ? esc(I18N_TESORERIA.si) : esc(I18N_TESORERIA.no)}</td><td>${btn}</td>`;
    tb.appendChild(tr);
  });
  tb.querySelectorAll('.btn-desact').forEach(b => {
    b.onclick = async () => {
      if (!confirm(I18N_TESORERIA.confirmDesact)) return;
      const s = await api('/api/tesoreria/' + b.dataset.id + '/desactivar', {method:'POST'});
      if (!s.ok) return alert(s.error);
      loadFisicas();
    };
  });
}
document.addEventListener('DOMContentLoaded', () => {
  loadFisicas();
  document.getElementById('form-fisica').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/tesoreria', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    ev.target.reset();
    loadFisicas();
  };
});
</script>
