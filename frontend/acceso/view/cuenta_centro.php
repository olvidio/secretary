<h1>Centro</h1>
<p class="muted">Centro de trabajo de esta sesión. Solo aparecen los centros de los que es secretario.</p>
<form id="form-centro" class="grid-form">
    <label>Centro
        <select name="centro_id" required></select>
    </label>
    <button type="submit">Guardar</button>
    <p class="ok" id="msg" hidden>Guardado</p>
    <p class="muted" id="sin-centros" hidden>Esta cuenta no es secretario de ningún centro.</p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || 'Error');
  const sel = document.querySelector('#form-centro [name="centro_id"]');
  const centros = r.centros || [];
  const btn = document.querySelector('#form-centro button');
  if (centros.length === 0) {
    document.getElementById('sin-centros').hidden = false;
    sel.disabled = true;
    btn.disabled = true;
    return;
  }
  centros.forEach((c) => {
    const o = document.createElement('option');
    o.value = String(c.centro_id);
    o.textContent = c.nombre || c.codigo || String(c.centro_id);
    if (r.centro_id === c.centro_id) o.selected = true;
    sel.appendChild(o);
  });
  document.getElementById('form-centro').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/centro', {method:'POST', body: {centro_id: Number(sel.value)}});
    if (!s.ok) return alert(s.error);
    if (s.siguiente && s.siguiente !== location.pathname) {
      location.href = s.siguiente;
      return;
    }
    document.getElementById('msg').hidden = false;
  });
});
</script>
