<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= _("Activar 2FA — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<form method="post" action="/totp-activar" class="login-box login-box--totp">
    <h1><?= _("Segundo factor") ?></h1>
    <p><?= _("Escanea el código QR con tu aplicación de autenticación (Google Authenticator, Aegis, etc.) y confirma con un código de 6 dígitos.") ?></p>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <?php if (!empty($uri)): ?>
        <div id="totp-qr" class="totp-qr" data-uri="<?= htmlspecialchars((string) $uri, ENT_QUOTES) ?>"></div>
    <?php endif; ?>
    <?php if (!empty($secreto)): ?>
        <details class="totp-manual">
            <summary><?= _("Introducir clave manualmente") ?></summary>
            <p class="muted totp-secret"><strong><?= htmlspecialchars((string) $secreto, ENT_QUOTES) ?></strong></p>
        </details>
    <?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label><?= _("Código") ?> <input name="codigo" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code"></label>
    <button type="submit"><?= _("Confirmar") ?></button>
</form>
<script src="/js/qrcode.min.js"></script>
<script src="/js/totp-activar.js"></script>
</body>
</html>
