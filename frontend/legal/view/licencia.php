<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Licencia") ?> — <?= _("Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<div class="login-box documento-legal">
    <h1><?= _("Licencia del programa") ?></h1>
    <p class="muted"><?= _("GNU General Public License v3.0 o posterior") ?></p>
    <pre class="documento-legal-cuerpo documento-legal-licencia"><?= htmlspecialchars((string) ($texto ?? ''), ENT_QUOTES) ?></pre>
    <p class="login-alt">
        <a href="/condiciones"><?= _("Condiciones de uso") ?></a>
        ·
        <a href="/privacidad"><?= _("Privacidad") ?></a>
        ·
        <a href="/registro"><?= _("Registrarse") ?></a>
        ·
        <a href="/login"><?= _("Entrar") ?></a>
    </p>
</div>
</body>
</html>
