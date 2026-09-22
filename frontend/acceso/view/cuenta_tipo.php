<h1><?= _("Tipo") ?></h1>
<p class="muted"><?= _("Solo para cuentas que son a la vez secretario de un centro y titular de un libro personal con la misma identidad. Cambia el modo de esta sesión (centro o Mis cuentas), sin borrar datos. Si tiene cuentas distintas (personal y secretario), salga y entre con la otra.") ?></p>
<form id="form-tipo">
    <label class="cuenta-tipo-op">
        <input type="radio" name="tipo" value="centro" required>
        <span><?= _("Secretario de centro (nivel 2)") ?></span>
    </label>
    <label class="cuenta-tipo-op">
        <input type="radio" name="tipo" value="persona">
        <span><?= _("Individual (nivel 1)") ?></span>
    </label>
    <button type="submit"><?= _("Guardar") ?></button>
    <p class="ok" id="msg" hidden><?= _("Guardado") ?></p>
    <p class="muted" id="aviso"></p>
</form>
<script>
const I18N_TIPO = {
  noCentro: <?= json_encode(_("Esta cuenta no es secretario de ningún centro."), JSON_UNESCAPED_UNICODE) ?>,
  noPersona: <?= json_encode(_("Esta identidad no tiene libro personal ni vínculo de persona."), JSON_UNESCAPED_UNICODE) ?>,
};
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
  if (!r.puede_cambiar_tipo) {
    location.href = r.nivel === 'persona' ? '/yo' : '/';
    return;
  }
  const form = document.getElementById('form-tipo');
  const aviso = document.getElementById('aviso');
  const radioCentro = form.querySelector('[value="centro"]');
  const radioPersona = form.querySelector('[value="persona"]');
  radioCentro.checked = r.nivel === 'centro';
  radioPersona.checked = r.nivel === 'persona';
  if (!r.puede_centro) {
    radioCentro.disabled = true;
    aviso.textContent = I18N_TIPO.noCentro;
  }
  if (!r.puede_persona) {
    radioPersona.disabled = true;
    aviso.textContent = (aviso.textContent ? aviso.textContent + ' ' : '')
      + I18N_TIPO.noPersona;
  }
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/tipo', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    location.href = s.siguiente || '/';
  });
});
</script>
