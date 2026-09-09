<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Códigos de recuperación — Secretario</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="login">
<?php
if (!isset($codigos) || !is_array($codigos)) {
    $codigos = [];
}
?>
<div class="login-box" style="width:28rem">
    <h1>Guarde estos códigos</h1>
    <p>Cada uno sirve una sola vez si pierde el autenticador. No se volverán a mostrar.</p>
    <ul>
        <?php foreach ($codigos as $c): ?>
            <li><code><?= htmlspecialchars((string) $c, ENT_QUOTES) ?></code></li>
        <?php endforeach; ?>
    </ul>
    <p><a href="<?= htmlspecialchars((string) ($siguiente ?? '/'), ENT_QUOTES) ?>">Continuar</a></p>
</div>
</body>
</html>
