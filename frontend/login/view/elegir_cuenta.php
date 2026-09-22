<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Elegir cuenta — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<?php
if (!isset($cuentas) || !is_array($cuentas)) {
    $cuentas = [];
}
?>
<form method="post" action="/elegir-cuenta" class="login-box">
    <h1><?= _("Elegir cuenta") ?></h1>
    <p class="muted"><?= _("Varias cuentas usan este correo. Elija con cuál entrar.") ?></p>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label><?= _("Cuenta") ?>
        <select name="identidad_id" required>
            <?php foreach ($cuentas as $c): ?>
                <option value="<?= (int) ($c['identidad_id'] ?? 0) ?>">
                    <?= htmlspecialchars((string) ($c['etiqueta'] ?? ''), ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit"><?= _("Entrar") ?></button>
    <p class="login-alt"><a href="/login"><?= _("Cancelar") ?></a></p>
</form>
</body>
</html>
