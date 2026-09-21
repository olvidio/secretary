<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) ($titulo ?? _('Documento')), ENT_QUOTES) ?> — <?= _("Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<div class="login-box documento-legal">
    <p class="muted"><?= _("Versión") ?> <?= htmlspecialchars((string) ($version ?? ''), ENT_QUOTES) ?></p>
    <div class="documento-legal-cuerpo">
        <?= $cuerpoHtml ?? '' ?>
    </div>
    <?php if (!empty($operador)): ?>
        <p class="muted">
            <?= _("Operador:") ?>
            <?= htmlspecialchars((string) $operador->nombre, ENT_QUOTES) ?>
            · <?= htmlspecialchars((string) $operador->email, ENT_QUOTES) ?>
            · <?= htmlspecialchars((string) $operador->direccion, ENT_QUOTES) ?>
        </p>
    <?php endif; ?>
    <p class="login-alt">
        <a href="/condiciones"><?= _("Condiciones de uso") ?></a>
        ·
        <a href="/privacidad"><?= _("Privacidad") ?></a>
        ·
        <a href="/licencia"><?= _("Licencia") ?></a>
        ·
        <a href="/registro"><?= _("Registrarse") ?></a>
        ·
        <a href="/login"><?= _("Entrar") ?></a>
    </p>
</div>
</body>
</html>
