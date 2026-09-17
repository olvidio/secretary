<h1><?= _("Traspasos de tesorería") ?></h1>

<h2><?= _("Mismo libro (caja ↔ banco u otra física)") ?></h2>
<form id="form-traspaso" class="grid-form">
    <label><?= _("Libro") ?>
        <select name="libro"><option value="P">P</option><option value="G">G</option></select>
    </label>
    <label><?= _("Origen (física)") ?> <select name="cuenta_fisica_origen_id" id="tr-origen"></select></label>
    <label><?= _("Destino (física)") ?> <select name="cuenta_fisica_destino_id" id="tr-destino"></select></label>
    <label><?= _("Fecha") ?> <input type="date" name="fecha" required></label>
    <label><?= _("Importe") ?> <input name="cantidad" required placeholder="0.00"></label>
    <label><?= _("Glosa") ?> <input name="glosa"></label>
    <button type="submit"><?= _("Registrar traspaso") ?></button>
</form>

<h2><?= _("Préstamo entre libros (misma física)") ?></h2>
<p><?= _("El dinero sale del libro origen y entra en el destino; el saldo físico no cambia.") ?></p>
<form id="form-prestamo" class="grid-form">
    <label><?= _("Cuenta física") ?> <select name="cuenta_fisica_id" id="pre-fisica"></select></label>
    <label><?= _("Libro origen (sale)") ?>
        <select name="libro_origen"><option value="G">G</option><option value="P">P</option></select>
    </label>
    <label><?= _("Libro destino (entra)") ?>
        <select name="libro_destino"><option value="P">P</option><option value="G">G</option></select>
    </label>
    <label><?= _("Fecha") ?> <input type="date" name="fecha" required></label>
    <label><?= _("Importe") ?> <input name="cantidad" required placeholder="0.00"></label>
    <label><?= _("Glosa") ?> <input name="glosa"></label>
    <button type="submit"><?= _("Registrar préstamo") ?></button>
</form>

<script>
const I18N_TRASPASOS = {
  traspasoOk: <?= json_encode(_("Traspaso registrado (asiento #%s)"), JSON_UNESCAPED_UNICODE) ?>,
  prestamoOk: <?= json_encode(_("Préstamo enlazado: asientos %s ↔ %s"), JSON_UNESCAPED_UNICODE) ?>,
};
async function loadFisicasSelects() {
  const r = await api('/api/tesoreria');
  const activas = (r.cuentas_fisicas || []).filter(f => f.activo);
  const opts = activas.map(f =>
    `<option value="${f.id}">${esc(f.tipo)} · ${esc(f.nombre)}</option>`).join('');
  ['tr-origen', 'tr-destino', 'pre-fisica'].forEach(id => {
    const el = document.getElementById(id);
    el.innerHTML = opts;
  });
}
document.addEventListener('DOMContentLoaded', async () => {
  const cfg = await api('/api/configuracion');
  document.querySelectorAll('[name=fecha]').forEach(el => { el.value = cfg.config.fecha_cierre; });
  await loadFisicasSelects();
  document.getElementById('form-traspaso').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/traspasos', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    alert(I18N_TRASPASOS.traspasoOk.replace('%s', s.asiento.numero));
  };
  document.getElementById('form-prestamo').onsubmit = async (ev) => {
    ev.preventDefault();
    const s = await api('/api/prestamos-libros', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    alert(I18N_TRASPASOS.prestamoOk
      .replace('%s', s.asiento_origen.id)
      .replace('%s', s.asiento_destino.id));
  };
});
</script>
