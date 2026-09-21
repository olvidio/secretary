<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Novedades") ?> — <?= _("Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<div class="login-box documento-legal">
    <h1><?= _("Novedades") ?></h1>
    <?php if (!empty($versionApp)): ?>
        <p class="muted"><?= _("Versión") ?> <?= htmlspecialchars((string) $versionApp, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <div class="documento-legal-cuerpo">
        <?= $cuerpoHtml ?? '' ?>
    </div>
    <p class="login-alt">
        <a href="/login"><?= _("Entrar") ?></a>
        ·
        <a href="/condiciones"><?= _("Condiciones de uso") ?></a>
        ·
        <a href="/privacidad"><?= _("Privacidad") ?></a>
        ·
        <a href="/licencia"><?= _("Licencia") ?></a>
    </p>
</div>
</body>
</html>
