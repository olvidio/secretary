<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Elegir centro — Secretario</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<?php
if (!isset($centros) || !is_array($centros)) {
    $centros = [];
}
?>
<form method="post" action="/elegir-centro" class="login-box">
    <h1>Centro</h1>
    <?php if (!empty($error)): ?>
        <p class="error"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p>
    <?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <label>Centro
        <select name="centro_id" required>
            <?php foreach ($centros as $c): ?>
                <option value="<?= (int) $c['centro_id'] ?>">
                    <?= htmlspecialchars((string) ($c['nombre'] ?? $c['codigo'] ?? ''), ENT_QUOTES) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit">Entrar</button>
</form>
</body>
</html>
