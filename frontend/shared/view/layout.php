<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) ($idioma ?? 'es'), ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <title>Secretario</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="layout-excel">
<header class="ribbon">
    <div class="ribbon-top">
        <strong><a href="/">Secretario</a></strong>
        <?php if (!empty($centroNombre)): ?>
            <span class="centro"><?= htmlspecialchars((string) $centroNombre, ENT_QUOTES) ?></span>
        <?php endif; ?>
        <?php include __DIR__ . '/_menu_usuario.php'; ?>
    </div>
    <nav>
        <?php foreach (($menuGrupos ?? []) as $grupo): ?>
            <div class="group">
                <span><?= htmlspecialchars((string) $grupo['label'], ENT_QUOTES) ?></span>
                <?php foreach ($grupo['items'] as $item): ?>
                    <a href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES) ?>"
                       class="<?= ($nav ?? '') === $item['nav'] ? 'on' : '' ?>"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
</header>
<main>
<?php
if (!empty($contentView) && is_file($contentView)) {
    include $contentView;
}
?>
</main>
<script src="/js/app.js"></script>
<script src="/js/editar-apunte.js"></script>
</body>
</html>
