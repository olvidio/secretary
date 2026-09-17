<h1><?= _("Idioma") ?></h1>
<p class="muted"><?= _("Elige el idioma de la interfaz: español o catalán.") ?></p>
<form id="form-idioma" class="grid-form">
    <label><?= _("Idioma") ?>
        <select name="idioma">
            <option value="es"><?= _("Español") ?></option>
            <option value="ca">Català</option>
        </select>
    </label>
    <button type="submit"><?= _("Guardar") ?></button>
    <p class="ok" id="msg" hidden><?= _("Guardado") ?></p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
  fillForm(document.getElementById('form-idioma'), r);
  document.getElementById('form-idioma').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/idioma', {method:'POST', body: formObj(ev.target)});
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) return alert(s.error);
    location.reload();
  });
});
</script>
