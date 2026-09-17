<h1><?= _("Segundo factor") ?></h1>
<p class="muted"><?= _("Autenticación TOTP (Google Authenticator, Aegis, etc.). Obligatorio para secretarios de centro; opcional en el libro personal.") ?></p>
<p id="totp-estado" class="ok" hidden><?= _("El segundo factor está activo.") ?></p>
<div id="totp-pendiente">
    <p id="totp-inactivo" class="muted"><?= _("Todavía no tiene segundo factor en esta cuenta.") ?></p>
    <button type="button" id="btn-totp-preparar"><?= _("Activar segundo factor") ?></button>
    <div id="totp-setup" hidden>
        <p><?= _("Escanea el código QR con tu aplicación de autenticación y confirma con un código de 6 dígitos.") ?></p>
        <div id="totp-qr" class="totp-qr"></div>
        <details class="totp-manual">
            <summary><?= _("Introducir clave manualmente") ?></summary>
            <p class="muted totp-secret"><strong id="totp-secreto"></strong></p>
        </details>
        <form id="form-totp" class="grid-form">
            <label><?= _("Código") ?> <input name="codigo" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label>
            <button type="submit"><?= _("Confirmar") ?></button>
        </form>
    </div>
</div>
<div id="totp-codigos" hidden>
    <p><strong><?= _("Guarde estos códigos de recuperación.") ?></strong> <?= _("Cada uno sirve una sola vez si pierde el autenticador. No se volverán a mostrar.") ?></p>
    <ul id="totp-codigos-lista"></ul>
</div>
<script src="/js/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const r = await api('/api/preferencias');
  if (!r.ok) return alert(r.error || <?= json_encode(_("Error"), JSON_UNESCAPED_UNICODE) ?>);
  if (r.totp_activo) {
    document.getElementById('totp-estado').hidden = false;
    document.getElementById('totp-pendiente').hidden = true;
    return;
  }
  let qr = null;
  document.getElementById('btn-totp-preparar').addEventListener('click', async () => {
    const p = await api('/api/preferencias/totp/preparar', {method:'POST', body:{}});
    if (!p.ok) return alert(p.error);
    document.getElementById('totp-inactivo').hidden = true;
    document.getElementById('btn-totp-preparar').hidden = true;
    document.getElementById('totp-setup').hidden = false;
    document.getElementById('totp-secreto').textContent = p.secreto || '';
    const cont = document.getElementById('totp-qr');
    cont.innerHTML = '';
    if (p.uri && typeof QRCode !== 'undefined') {
      qr = new QRCode(cont, {
        text: p.uri,
        width: 200,
        height: 200,
        colorDark: '#1a202c',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M,
      });
    }
  });
  document.getElementById('form-totp').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const s = await api('/api/preferencias/totp/confirmar', {method:'POST', body: formObj(ev.target)});
    if (!s.ok) return alert(s.error);
    document.getElementById('totp-pendiente').hidden = true;
    document.getElementById('totp-estado').hidden = false;
    const ul = document.getElementById('totp-codigos-lista');
    ul.innerHTML = '';
    (s.codigos || []).forEach((c) => {
      const li = document.createElement('li');
      const code = document.createElement('code');
      code.textContent = c;
      li.appendChild(code);
      ul.appendChild(li);
    });
    document.getElementById('totp-codigos').hidden = false;
  });
});
</script>
