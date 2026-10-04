<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Revise su correo — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<div class="login-box">
    <h1><?= _("Revise su correo") ?></h1>
    <p class="ok"><?= _("Si hay una cuenta con ese dato y el correo está confirmado, le hemos enviado un enlace para elegir una contraseña nueva. Caduca en 2 horas.") ?></p>
    <p class="muted"><?= _("Si no llega el mensaje, puede pedirlo otra vez. El enlace anterior deja de valer.") ?></p>
    <p class="login-alt"><a href="/olvide-contrasena"><?= _("Pedir otro enlace") ?></a></p>
    <p class="login-alt"><a href="/login"><?= _("Volver a entrar") ?></a></p>
    <?php include dirname(__DIR__, 2) . '/shared/view/_pie_legal.php'; ?>
</div>
</body>
</html>
