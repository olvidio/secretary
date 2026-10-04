<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= !empty($valido) ? _("Nueva contraseña — Secretario") : _("Enlace no válido — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<?php if (!empty($valido)): ?>
<form method="post" action="/restablecer-contrasena" class="login-box">
    <h1><?= _("Nueva contraseña") ?></h1>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <p class="muted"><?= htmlspecialchars(sprintf(_("Va a cambiar la contraseña de %s."), (string) ($etiqueta ?? '')), ENT_QUOTES) ?></p>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars((string) ($token ?? ''), ENT_QUOTES) ?>">
    <label><?= _("Contraseña nueva") ?> <input type="password" name="password" required minlength="6" autofocus autocomplete="new-password"></label>
    <label><?= _("Repetir contraseña") ?> <input type="password" name="password_confirm" required minlength="6" autocomplete="new-password"></label>
    <button type="submit"><?= _("Guardar contraseña") ?></button>
    <p class="login-alt"><a href="/login"><?= _("Volver a entrar") ?></a></p>
    <?php include dirname(__DIR__, 2) . '/shared/view/_pie_legal.php'; ?>
</form>
<?php else: ?>
<div class="login-box">
    <h1><?= _("Enlace no válido") ?></h1>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <p class="login-alt"><a href="/olvide-contrasena"><?= _("Solicitar otro enlace") ?></a></p>
    <p class="login-alt"><a href="/login"><?= _("Volver a entrar") ?></a></p>
    <?php include dirname(__DIR__, 2) . '/shared/view/_pie_legal.php'; ?>
</div>
<?php endif; ?>
</body>
</html>
