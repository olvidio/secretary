<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Confirme su correo — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<div class="login-box">
    <h1><?= _("Revise su correo") ?></h1>
    <?php if (!empty($ok)): ?>
        <p class="ok"><?= htmlspecialchars((string) $ok, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <p class="muted">
        <?= _("Le hemos enviado un mensaje a") ?>
        <strong><?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES) ?></strong>.
        <?= _("Abra el enlace para activar su cuenta.") ?>
    </p>
    <p class="muted"><?= _("El enlace caduca en 48 horas.") ?></p>
    <form method="post" action="/registro/reenviar" class="grid-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES) ?>">
        <button type="submit"><?= _("Reenviar correo") ?></button>
    </form>
    <p class="login-alt"><a href="/login"><?= _("Volver a entrar") ?></a></p>
</div>
</body>
</html>
