<h1>Tipo</h1>
<p class="muted">Nivel 2 es secretario de centro (el programa actual de <em>scl</em>). Nivel 1 es el libro personal de una persona.</p>
<form id="form-tipo">
    <label class="cuenta-tipo-op">
        <input type="radio" name="tipo" value="centro" required>
        <span>Secretario de centro (nivel 2)</span>
    </label>
    <label class="cuenta-tipo-op">
        <input type="radio" name="tipo" value="persona">
        <span>Individual (nivel 1)</span>
    </label>
    <button type="submit">Guardar</button>
    <p class="ok" id="msg" hidden>Guardado</p>
    <p class="muted" id="aviso"></p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || 'Error');
  const form = document.getElementById('form-tipo');
  const aviso = document.getElementById('aviso');
  const radioCentro = form.querySelector('[value="centro"]');
  const radioPersona = form.querySelector('[value="persona"]');
  radioCentro.checked = r.nivel === 'centro';
  radioPersona.checked = r.nivel === 'persona';
  if (!r.puede_centro) {
    radioCentro.disabled = true;
    aviso.textContent = 'Esta cuenta no es secretario de ningún centro.';
  }
  if (!r.puede_persona) {
    radioPersona.disabled = true;
    aviso.textContent = (aviso.textContent ? aviso.textContent + ' ' : '')
      + 'No está vinculada a una persona; el libro personal se crea al poner el correo en Nombres.';
  }
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/tipo', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    location.href = s.siguiente || '/';
  });
});
</script>
