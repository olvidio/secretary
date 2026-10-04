<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Olvidé la contraseña — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<form method="post" action="/olvide-contrasena" class="login-box">
    <h1><?= _("Olvidé la contraseña") ?></h1>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <p class="muted"><?= _("Escriba el alias o el correo de la cuenta. Si el correo está confirmado, le enviaremos un enlace para elegir una contraseña nueva.") ?></p>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label><?= _("Alias o correo") ?> <input name="usuario" required autofocus autocomplete="username" value="<?= htmlspecialchars((string) ($usuario ?? ''), ENT_QUOTES) ?>"></label>
    <button type="submit"><?= _("Enviar enlace") ?></button>
    <p class="login-alt"><a href="/login"><?= _("Volver a entrar") ?></a></p>
    <?php include dirname(__DIR__, 2) . '/shared/view/_pie_legal.php'; ?>
</form>
</body>
</html>
