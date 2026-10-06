<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Elegir ámbito — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<?php
if (!isset($opciones) || !is_array($opciones)) {
    $opciones = [];
}
?>
<form method="post" action="/elegir-ambito" class="login-box">
    <h1><?= _("Ámbito") ?></h1>
    <p class="muted"><?= _("Elija con qué quiere trabajar en esta sesión.") ?></p>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label><?= _("Ámbito") ?>
        <select name="ambito" required>
            <?php foreach ($opciones as $o): ?>
                <option value="<?= htmlspecialchars((string) ($o['valor'] ?? ''), ENT_QUOTES) ?>">
                    <?= htmlspecialchars((string) ($o['etiqueta'] ?? ''), ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit"><?= _("Entrar") ?></button>
</form>
</body>
</html>
