<?php
$gruposBurger = is_array($menuGrupos ?? null) ? $menuGrupos : [];
$grupoActivo = (string) ($menuGrupoActivo ?? '');
$navActual = (string) ($nav ?? '');
$itemsActivos = [];
foreach ($gruposBurger as $g) {
    if ((string) $g['id'] === $grupoActivo) {
        $itemsActivos = $g['items'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) ($idioma ?? 'es'), ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <title><?= _("Secretario") ?></title>
    <link rel="stylesheet" href="/css/app.css?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/css/app.css') ?: 0) ?>">
</head>
<body class="layout-burger">
<button type="button" class="burger-toggle" id="burgerToggle" aria-label="<?= htmlspecialchars(_("Menú"), ENT_QUOTES) ?>">☰</button>
<div class="burger-overlay" id="burgerOverlay" hidden></div>
<aside class="burger-sidebar" id="burgerSidebar">
    <div class="burger-sidebar-header">
        <a href="/"><?= _("Secretario") ?></a>
    </div>
    <nav class="burger-groups" id="burgerGroups">
        <ul>
            <?php foreach ($gruposBurger as $grupo): ?>
                <li>
                    <a href="#" data-group="<?= htmlspecialchars((string) $grupo['id'], ENT_QUOTES) ?>"
                       class="<?= (string) $grupo['id'] === $grupoActivo ? 'active' : '' ?>"><?= htmlspecialchars((string) $grupo['label'], ENT_QUOTES) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <div class="burger-sidebar-foot">
        <a href="/logout"><?= _("Salir") ?></a>
    </div>
</aside>
<div class="burger-shell">
    <header class="burger-top">
        <nav>
            <ul class="burger-items" id="burgerItems">
                <?php foreach ($itemsActivos as $item): ?>
                    <li>
                        <a href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES) ?>"
                           class="<?= $navActual === $item['nav'] ? 'on' : '' ?>"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="burger-top-right">
            <?php if (!empty($centroNombre)): ?>
                <span class="centro"><?= htmlspecialchars((string) $centroNombre, ENT_QUOTES) ?></span>
            <?php endif; ?>
            <?php include __DIR__ . '/_menu_usuario.php'; ?>
        </div>
    </header>
    <main>
<?php
if (!empty($contentView) && is_file($contentView)) {
    include $contentView;
}
?>
    </main>
    <?php include __DIR__ . '/_pie_legal.php'; ?>
</div>
<script>
window.secretarioMenus = <?= json_encode([
    'nav' => $navActual,
    'grupoActivo' => $grupoActivo,
    'grupos' => $gruposBurger,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<?php include __DIR__ . '/_js_i18n.php'; ?>
<script src="/js/app.js?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/js/app.js') ?: 0) ?>"></script>
<script src="/js/editar-apunte.js?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/js/editar-apunte.js') ?: 0) ?>"></script>
<script src="/js/layout_burger.js?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/js/layout_burger.js') ?: 0) ?>"></script>
</body>
</html>
