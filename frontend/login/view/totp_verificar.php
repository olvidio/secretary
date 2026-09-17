<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= _("Código TOTP — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<form method="post" action="/totp-verificar" class="login-box">
    <h1><?= _("Segundo factor") ?></h1>
    <p><?= _("Introduzca el código de 6 dígitos o un código de recuperación.") ?></p>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label><?= _("Código") ?> <input name="codigo" required autofocus autocomplete="one-time-code"></label>
    <button type="submit"><?= _("Entrar") ?></button>
</form>
</body>
</html>
