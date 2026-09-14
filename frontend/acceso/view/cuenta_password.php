<h1>Contraseña</h1>
<p class="muted">Cambie la contraseña de esta cuenta. Hace falta la actual para confirmar.</p>
<form id="form-password" class="grid-form">
    <label>Actual <input name="password_actual" type="password" required autocomplete="current-password"></label>
    <label>Nueva <input name="password" type="password" required autocomplete="new-password" minlength="6"></label>
    <label>Repetir nueva <input name="password_confirm" type="password" required autocomplete="new-password" minlength="6"></label>
    <button type="submit">Guardar</button>
    <p class="ok" id="msg" hidden>Contraseña actualizada</p>
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('form-password').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/password', {method:'POST', body: formObj(ev.target)});
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) return alert(s.error);
    ev.target.reset();
  });
});
</script>
