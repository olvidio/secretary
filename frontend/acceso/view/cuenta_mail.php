<h1><?= _("Mail") ?></h1>
<p class="muted"><?= _("Correo de esta cuenta. Sirve para entrar y, si está vinculada a un nombre, también es el del libro personal.") ?></p>
<form id="form-mail" class="grid-form">
    <label><?= _("Correo") ?> <input name="email" type="email" required autocomplete="email"></label>
    <button type="submit"><?= _("Guardar") ?></button>
    <p class="ok" id="msg" hidden><?= _("Guardado") ?></p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
  fillForm(document.getElementById('form-mail'), r);
  document.getElementById('form-mail').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/mail', {method:'POST', body: formObj(ev.target)});
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) alert(s.error);
  });
});
</script>
