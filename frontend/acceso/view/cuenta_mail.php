<h1>Mail</h1>
<p class="muted">Correo de esta cuenta. Sirve para entrar y, si está vinculada a un nombre, también es el del libro personal.</p>
<form id="form-mail" class="grid-form">
    <label>Correo <input name="email" type="email" required autocomplete="email"></label>
    <button type="submit">Guardar</button>
    <p class="ok" id="msg" hidden>Guardado</p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || 'Error');
  fillForm(document.getElementById('form-mail'), r);
  document.getElementById('form-mail').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/mail', {method:'POST', body: formObj(ev.target)});
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) alert(s.error);
  });
});
</script>
