<h1><?= _("Ámbito") ?></h1>
<p class="muted"><?= _("Cambia entre su libro personal y los centros de los que es secretario, en un solo paso.") ?></p>
<form id="form-ambito" class="grid-form">
    <label><?= _("Ámbito") ?>
        <select name="ambito" required></select>
    </label>
    <button type="submit"><?= _("Cambiar") ?></button>
    <p class="ok" id="msg" hidden><?= _("Guardado") ?></p>
</form>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
  if (!r.puede_elegir_ambito) {
    location.href = r.nivel === 'persona' ? '/yo' : '/';
    return;
  }
  const sel = document.querySelector('#form-ambito [name="ambito"]');
  const opciones = r.ambitos || [];
  opciones.forEach((o) => {
    const opt = document.createElement('option');
    opt.value = o.valor;
    opt.textContent = o.etiqueta;
    if (r.ambito_actual === o.valor) opt.selected = true;
    sel.appendChild(opt);
  });
  document.getElementById('form-ambito').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/ambito', { method: 'POST', body: { ambito: sel.value } });
    if (!s.ok) return alert(s.error);
    if (s.siguiente && s.siguiente !== location.pathname) {
      location.href = s.siguiente;
      return;
    }
    document.getElementById('msg').hidden = false;
  });
});
</script>
