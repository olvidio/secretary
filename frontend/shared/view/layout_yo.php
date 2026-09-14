<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) ($idioma ?? 'es'), ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <title>Mis cuentas — Secretario</title>
    <link rel="stylesheet" href="/css/app.css?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/css/app.css') ?: 0) ?>">
</head>
<body class="yo" data-nav="<?= htmlspecialchars((string) ($nav ?? 'yo'), ENT_QUOTES) ?>">
<header class="yo-top">
    <strong>Mis cuentas</strong>
    <?php include __DIR__ . '/_menu_usuario.php'; ?>
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
    <a href="/yo/banco" class="<?= ($nav ?? '') === 'yo-banco' ? 'on' : '' ?>">Banco</a>
    <a href="/yo/categorias" class="<?= ($nav ?? '') === 'yo-categorias' ? 'on' : '' ?>">Categorías</a>
    <?php if (!empty($mostrarRemesas)): ?>
    <a href="/yo/remesas" class="<?= ($nav ?? '') === 'yo-remesas' ? 'on' : '' ?>">Remesa</a>
    <?php endif; ?>
    <a href="/yo/cierre" class="<?= ($nav ?? '') === 'yo-cierre' ? 'on' : '' ?>">Cierre</a>
    <a href="/yo/centros" class="<?= ($nav ?? '') === 'yo-centros' ? 'on' : '' ?>">Centros</a>
</nav>
<script src="/js/app.js?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/js/app.js') ?: 0) ?>"></script>
<script src="/js/yo.js?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/js/yo.js') ?: 0) ?>"></script>
</body>
</html>
