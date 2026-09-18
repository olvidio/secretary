<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Confirmar correo — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<div class="login-box">
    <h1><?= !empty($ok) ? _("Correo confirmado") : _("No se pudo confirmar") ?></h1>
    <?php if (!empty($mensaje)): ?>
        <p class="<?= !empty($ok) ? 'ok' : 'error' ?>"><?= htmlspecialchars((string) $mensaje, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <p class="login-alt"><a href="/login"><?= _("Ir al login") ?></a></p>
    <?php include dirname(__DIR__, 2) . '/shared/view/_pie_legal.php'; ?>
</div>
</body>
</html>
