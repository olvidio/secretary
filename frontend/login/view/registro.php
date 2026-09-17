<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Registrarse — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<?php
$centros = is_array($centros ?? null) ? $centros : [];
$centroId = (int) ($centroId ?? 0);
$sinCentro = $centros === [];
$variosCentros = count($centros) > 1;
?>
<form method="post" action="/registro" class="login-box">
    <h1><?= _("Registrarse") ?></h1>
    <p class="muted"><?= _("Cuenta personal (libro propio). Un secretario de centro se da de alta en Centros.") ?></p>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <?php if ($sinCentro): ?>
        <p class="error"><?= _("Todavía no hay ningún centro. Pida a un secretario que lo cree.") ?></p>
    <?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label><?= _("Usuario") ?> <input name="usuario" required autofocus autocomplete="username" pattern="[a-zA-Z][a-zA-Z0-9._-]{1,31}" title="<?= htmlspecialchars(_("Letra inicial y 2-32 caracteres"), ENT_QUOTES) ?>" value="<?= htmlspecialchars((string) ($usuario ?? ''), ENT_QUOTES) ?>"></label>
    <label><?= _("Correo") ?> <input type="email" name="email" required autocomplete="email" value="<?= htmlspecialchars((string) ($email ?? ''), ENT_QUOTES) ?>"></label>
    <label><?= _("Nombre") ?> <input name="nombre" autocomplete="name" value="<?= htmlspecialchars((string) ($nombre ?? ''), ENT_QUOTES) ?>"></label>
    <?php if ($variosCentros): ?>
        <label><?= _("Centro") ?>
            <select name="centro_id" required>
                <option value=""><?= _("Elegir…") ?></option>
                <?php foreach ($centros as $c): ?>
                    <?php if (!is_array($c)) {
                        continue;
                    } ?>
                    <option value="<?= (int) ($c['id'] ?? 0) ?>"<?= ((int) ($c['id'] ?? 0) === $centroId) ? ' selected' : '' ?>>
                        <?= htmlspecialchars((string) ($c['nombre'] ?? $c['codigo'] ?? ''), ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php elseif (count($centros) === 1): ?>
        <input type="hidden" name="centro_id" value="<?= (int) ($centros[0]['id'] ?? 0) ?>">
    <?php endif; ?>
    <label><?= _("Contraseña") ?> <input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
    <label><?= _("Repetir contraseña") ?> <input type="password" name="password_confirm" required minlength="6" autocomplete="new-password"></label>
    <button type="submit"<?= $sinCentro ? ' disabled' : '' ?>><?= _("Crear cuenta") ?></button>
    <p class="login-alt"><a href="/login"><?= _("Volver a entrar") ?></a></p>
</form>
</body>
</html>
