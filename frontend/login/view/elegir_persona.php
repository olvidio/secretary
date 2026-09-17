<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= _("Elegir persona — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<?php
if (!isset($personas) || !is_array($personas)) {
    $personas = [];
}
?>
<form method="post" action="/elegir-persona" class="login-box">
    <h1><?= _("Persona") ?></h1>
    <p class="muted"><?= _("Tiene varios vínculos. Elija con cuál quiere trabajar en esta sesión.") ?></p>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label><?= _("Centro / persona") ?>
        <select name="persona_id" required>
            <?php foreach ($personas as $p): ?>
                <option value="<?= (int) $p['persona_id'] ?>">
                    <?= htmlspecialchars(
                        trim(
                            (string) ($p['centro_nombre'] ?? $p['centro_codigo'] ?? '')
                            . ' — '
                            . (string) ($p['iniciales'] ?? '')
                            . ' '
                            . (string) ($p['nombre_completo'] ?? ''),
                        ),
                        ENT_QUOTES,
                    ) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit"><?= _("Entrar") ?></button>
</form>
</body>
</html>
