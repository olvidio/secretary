<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Activar 2FA — Secretario</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<form method="post" action="/totp-activar" class="login-box" style="width:28rem">
    <h1>Segundo factor</h1>
    <p>Las cuentas de centro exigen TOTP (aplicación de autenticación). Añada esta clave y confirme con un código de 6 dígitos.</p>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <p class="muted" style="word-break:break-all">Clave: <strong><?= htmlspecialchars((string) ($secreto ?? ''), ENT_QUOTES) ?></strong></p>
    <p class="muted" style="word-break:break-all;font-size:.8rem"><?= htmlspecialchars((string) ($uri ?? ''), ENT_QUOTES) ?></p>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label>Código <input name="codigo" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code"></label>
    <button type="submit">Confirmar</button>
</form>
</body>
</html>
