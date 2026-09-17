<div class="yo-month">
    <button type="button" id="yo-mes-ant" aria-label="<?= htmlspecialchars(_("Mes anterior"), ENT_QUOTES) ?>">‹</button>
    <h1 id="yo-mes-titulo"><?= _("Movimientos") ?></h1>
    <button type="button" id="yo-mes-sig" aria-label="<?= htmlspecialchars(_("Mes siguiente"), ENT_QUOTES) ?>">›</button>
</div>
<p id="yo-lista-vacia" class="muted" hidden><?= _("No hay movimientos este mes.") ?></p>
<ul id="yo-lista" class="yo-lista"></ul>
<?php include __DIR__ . '/_form_movimiento.php'; ?>
