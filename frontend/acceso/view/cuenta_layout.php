<h1>Layout</h1>
<p class="muted">Disposición de los menús del centro (tipo excel o burger). El libro personal no cambia.</p>
<form id="form-layout" class="grid-form">
    <label class="cuenta-tipo-op"><input type="radio" name="layout" value="excel" required> Tipo excel</label>
    <label class="cuenta-tipo-op"><input type="radio" name="layout" value="burger"> Burger</label>
    <button type="submit">Guardar</button>
    <p class="ok" id="msg" hidden>Guardado</p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || 'Error');
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
