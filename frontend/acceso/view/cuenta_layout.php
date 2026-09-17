<h1><?= _("Layout") ?></h1>
<p class="muted"><?= _("Disposición de los menús del centro (tipo excel o burger). El libro personal no cambia.") ?></p>
<form id="form-layout" class="grid-form">
    <label class="cuenta-tipo-op"><input type="radio" name="layout" value="excel" required> <?= _("Tipo excel") ?></label>
    <label class="cuenta-tipo-op"><input type="radio" name="layout" value="burger"> <?= _("Burger") ?></label>
    <button type="submit"><?= _("Guardar") ?></button>
    <p class="ok" id="msg" hidden><?= _("Guardado") ?></p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
  const form = document.getElementById('form-layout');
  const radio = form.querySelector(`[name="layout"][value="${r.layout}"]`);
  if (radio) radio.checked = true;
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/layout', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    location.reload();
  });
});
</script>
