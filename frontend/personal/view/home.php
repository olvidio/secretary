<div class="yo-month">
    <button type="button" id="yo-mes-ant" aria-label="<?= htmlspecialchars(_("Mes anterior"), ENT_QUOTES) ?>">‹</button>
    <h1 id="yo-mes-titulo"><?= _("Mes") ?></h1>
    <button type="button" id="yo-mes-sig" aria-label="<?= htmlspecialchars(_("Mes siguiente"), ENT_QUOTES) ?>">›</button>
</div>
<section class="yo-hero">
    <div class="yo-chart-wrap">
        <div id="yo-chart" class="yo-chart" role="img" aria-label="<?= htmlspecialchars(_("Distribución del mes"), ENT_QUOTES) ?>"></div>
        <div class="yo-chart-center">
            <span class="yo-chart-label"><?= _("Saldo") ?></span>
            <strong id="yo-saldo">0,00</strong>
        </div>
    </div>
    <div class="yo-saldos">
        <div><span><?= _("Caja") ?></span><strong id="yo-caja">0,00</strong></div>
        <div><span><?= _("Banco") ?></span><strong id="yo-banco">0,00</strong></div>
    </div>
    <div class="yo-flujo">
        <div class="ing"><span><?= _("Ingresos") ?></span><strong id="yo-ingresos">0,00</strong></div>
        <div class="gas"><span><?= _("Gastos") ?></span><strong id="yo-gastos">0,00</strong></div>
    </div>
</section>
<div class="yo-fabs">
    <button type="button" id="yo-btn-ingreso" class="yo-fab ing">+ <?= _("Ingreso") ?></button>
    <button type="button" id="yo-btn-gasto" class="yo-fab gas">− <?= _("Gasto") ?></button>
</div>
<p class="yo-traspaso-link"><button type="button" id="yo-btn-traspaso"><?= _("Traspaso caja ↔ banco") ?></button></p>
<ul id="yo-leyenda" class="yo-leyenda"></ul>

<?php include __DIR__ . '/_form_movimiento.php'; ?>
