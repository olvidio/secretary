<h1>Idioma</h1>
<p class="muted">Preferencia de idioma de esta cuenta. Por ahora la interfaz sigue en español; se guarda para cuando haya traducciones.</p>
<form id="form-idioma" class="grid-form">
    <label>Idioma
        <select name="idioma">
            <option value="es">Español</option>
            <option value="ca">Català</option>
        </select>
    </label>
    <button type="submit">Guardar</button>
    <p class="ok" id="msg" hidden>Guardado</p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || 'Error');
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
