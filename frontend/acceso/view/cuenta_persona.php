<h1><?= _("Persona activa") ?></h1>
<p class="muted"><?= _("Elige con qué nombre en qué centro (tipo n) trabajas en Mis cuentas. Solo aparecen vínculos ya aprobados; el libro propio sin centro no sale aquí.") ?></p>
<form id="form-persona" class="grid-form">
    <label><?= _("Persona") ?>
        <select name="persona_id" required></select>
    </label>
    <button type="submit"><?= _("Guardar") ?></button>
    <p class="ok" id="msg" hidden><?= _("Guardado") ?></p>
    <p class="muted" id="sin-personas" hidden><?= _("Esta cuenta no tiene ningún vínculo de persona.") ?></p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
  if (!r.puede_elegir_persona_activa) {
    location.href = '/yo';
    return;
  }
  const sel = document.querySelector('#form-persona [name="persona_id"]');
  const personas = r.personas || [];
  const btn = document.querySelector('#form-persona button');
  if (personas.length === 0) {
    document.getElementById('sin-personas').hidden = false;
    sel.disabled = true;
    btn.disabled = true;
    return;
  }
  personas.forEach((p) => {
    const o = document.createElement('option');
    o.value = String(p.persona_id);
    const centro = p.centro_nombre || p.centro_codigo || '';
    const nombre = p.nombre_completo || p.iniciales || String(p.persona_id);
    o.textContent = centro ? `${centro} — ${nombre}` : nombre;
    if (r.persona_id === p.persona_id) o.selected = true;
    sel.appendChild(o);
  });
  document.getElementById('form-persona').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/persona', {method:'POST', body: {persona_id: Number(sel.value)}});
    if (!s.ok) return alert(s.error);
    if (s.siguiente && s.siguiente !== location.pathname) {
      location.href = s.siguiente;
      return;
    }
    document.getElementById('msg').hidden = false;
  });
});
</script>
