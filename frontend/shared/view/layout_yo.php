<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <title>Mis cuentas — Secretario</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="yo" data-nav="<?= htmlspecialchars((string) ($nav ?? 'yo'), ENT_QUOTES) ?>">
<header class="yo-top">
    <strong>Mis cuentas</strong>
    <span class="user"><?= htmlspecialchars((string) ($usuario ?? ''), ENT_QUOTES) ?></span>
    <a href="/logout">Salir</a>
</header>
<main class="yo-main">
<?php
if (!empty($contentView) && is_file($contentView)) {
    include $contentView;
}
?>
</main>
<nav class="yo-tabbar">
    <a href="/yo" class="<?= ($nav ?? '') === 'yo' ? 'on' : '' ?>">Resumen</a>
    <a href="/yo/movimientos" class="<?= ($nav ?? '') === 'yo-movimientos' ? 'on' : '' ?>">Lista</a>
    <a href="/yo/categorias" class="<?= ($nav ?? '') === 'yo-categorias' ? 'on' : '' ?>">Categorías</a>
    <a href="/yo/remesas" class="<?= ($nav ?? '') === 'yo-remesas' ? 'on' : '' ?>">Remesa</a>
</nav>
<script src="/js/app.js"></script>
<script src="/js/yo.js"></script>
</body>
</html>
