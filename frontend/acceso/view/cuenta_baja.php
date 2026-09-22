<h1><?= _("Dar de baja la cuenta") ?></h1>
<p class="muted"><?= _("Elimina su cuenta personal y el libro propio. Las remesas que ya haya enviado al centro siguen en manos del secretario. Esta acción no se puede deshacer.") ?></p>
<p class="muted" id="aviso-pendiente" hidden></p>
<div id="resumen-baja" class="muted"></div>
<p class="muted" id="conservacion" hidden></p>
<button type="button" class="peligro" id="btn-solicitar"><?= _("Enviar correo de confirmación") ?></button>
<p class="ok" id="msg" hidden></p>
<script>
const I18N_BAJA = {
  error: <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>,
  noPersonal: <?= json_encode(_("Esta cuenta no puede darse de baja desde aquí."), JSON_UNESCAPED_UNICODE) ?>,
  confirm1: <?= json_encode(_("¿Seguro que desea solicitar la baja de su cuenta?"), JSON_UNESCAPED_UNICODE) ?>,
  confirm2: <?= json_encode(_("Se enviará un correo con un enlace. Hasta abrirlo, la cuenta sigue activa."), JSON_UNESCAPED_UNICODE) ?>,
  pendiente: <?= json_encode(_("Ya hay una solicitud pendiente. Revise su correo antes del %s o solicite de nuevo tras caducar."), JSON_UNESCAPED_UNICODE) ?>,
};
async function cargarResumen() {
  const r = await api('/api/preferencias/baja');
  if (!r.ok) return alert(r.error || I18N_BAJA.error);
  if (!r.puede_borrar) {
    document.getElementById('btn-solicitar').disabled = true;
    document.getElementById('resumen-baja').textContent = r.motivo_bloqueo || I18N_BAJA.noPersonal;
    return;
  }
  document.getElementById('resumen-baja').textContent = r.texto_datos || '';
  const cons = document.getElementById('conservacion');
  if (r.texto_conservacion) {
    cons.hidden = false;
    cons.textContent = r.texto_conservacion;
  }
  const pref = await api('/api/preferencias');
  if (pref.ok && pref.baja_pendiente_hasta) {
    const aviso = document.getElementById('aviso-pendiente');
    aviso.hidden = false;
    aviso.textContent = I18N_BAJA.pendiente.replace('%s', pref.baja_pendiente_hasta);
  }
}
document.addEventListener('DOMContentLoaded', () => {
  cargarResumen();
  document.getElementById('btn-solicitar').onclick = async () => {
    if (!confirm(I18N_BAJA.confirm1 + '\n\n' + I18N_BAJA.confirm2)) return;
    const msg = document.getElementById('msg');
    msg.hidden = true;
    const s = await api('/api/preferencias/baja/solicitar', { method: 'POST', body: { confirmar: true } });
    if (!s.ok) return alert(s.error);
    msg.hidden = false;
    msg.textContent = s.mensaje || '';
    await cargarResumen();
  };
});
</script>
