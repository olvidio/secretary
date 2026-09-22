<h1><?= _("Mail") ?></h1>
<p class="muted"><?= _("Correo de esta cuenta. Sirve para entrar y, si está vinculada a un nombre, también es el del libro personal.") ?></p>
<p class="muted" id="aviso-pendiente" hidden></p>
<form id="form-mail" class="grid-form">
    <label><?= _("Correo") ?> <input name="email" type="email" required autocomplete="email"></label>
    <button type="submit"><?= _("Guardar") ?></button>
    <p class="ok" id="msg" hidden></p>
</form>
<script>
const I18N_MAIL = {
  error: <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>,
  pendiente: <?= json_encode(_("Pendiente de confirmación: %s. Revise ese buzón y abra el enlace."), JSON_UNESCAPED_UNICODE) ?>,
};
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || I18N_MAIL.error);
  fillForm(document.getElementById('form-mail'), r);
  const aviso = document.getElementById('aviso-pendiente');
  if (r.email_pendiente) {
    aviso.hidden = false;
    aviso.textContent = I18N_MAIL.pendiente.replace('%s', r.email_pendiente);
  }
  document.getElementById('form-mail').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const msg = document.getElementById('msg');
    msg.hidden = true;
    const s = await api('/api/preferencias/mail', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    msg.hidden = false;
    msg.textContent = s.mensaje || '';
    if (s.pendiente_confirmacion) {
      aviso.hidden = false;
      aviso.textContent = I18N_MAIL.pendiente.replace('%s', formObj(ev.target).email);
      fillForm(document.getElementById('form-mail'), { email: s.email });
    } else {
      aviso.hidden = true;
      fillForm(document.getElementById('form-mail'), { email: s.email });
    }
  });
});
</script>
