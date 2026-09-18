<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) ($idioma ?? 'es'), ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <title><?= _("Administración — Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/css/app.css') ?: 0) ?>">
</head>
<body class="admin" data-nav="<?= htmlspecialchars((string) ($nav ?? 'admin'), ENT_QUOTES) ?>">
<header class="admin-top">
    <strong><?= _("Administración") ?></strong>
    <nav class="admin-nav">
        <?php foreach ($menuItems ?? [] as $item): ?>
            <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>"
               class="<?= ($nav ?? '') === $item['nav'] ? 'on' : '' ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES) ?></a>
        <?php endforeach; ?>
    </nav>
    <span class="muted"><?= htmlspecialchars((string) ($usuario ?? ''), ENT_QUOTES) ?></span>
    <a href="/logout"><?= _("Salir") ?></a>
</header>
<main class="admin-main">
<?php
if (!empty($contentView) && is_file($contentView)) {
    include $contentView;
}
?>
</main>
<?php include __DIR__ . '/_js_i18n.php'; ?>
<script src="/js/app.js?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/js/app.js') ?: 0) ?>"></script>
</body>
</html>
