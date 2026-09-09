<h1>Fecha de cierre</h1>
<p>Tras imprimir el 613, pase la fecha de cierre al mes siguiente. La entrada de apuntes usa este mes por defecto.</p>
<form id="form-cierre" class="grid-form">
    <label>Fecha cierre <input name="fecha_cierre" type="date" required></label>
    <button type="submit">Guardar</button>
    <p id="msg" class="ok" hidden>Guardado</p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/configuracion');
  document.querySelector('[name=fecha_cierre]').value = r.config.fecha_cierre;
  document.getElementById('form-cierre').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/configuracion', {method:'POST', body: formObj(ev.target)});
    document.getElementById('msg').hidden = !s.ok;
    if (!s.ok) alert(s.error);
  });
});
</script>
