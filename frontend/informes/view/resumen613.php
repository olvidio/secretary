<?php $cuenta = $cuentaInforme ?? 'P'; ?>
<div class="informe-613-wrap">
    <h1 class="print-hide"><?= sprintf(_("Resumen mensual 613 %s"), htmlspecialchars($cuenta, ENT_QUOTES)) ?></h1>
    <p class="print-hide informe-613-ayuda"><?= _("Las celdas azules admiten valores introducidos manualmente; se guardan al salir del campo.") ?></p>
    <p class="print-hide informe-613-acciones">
        <a href="/arqueo-<?= strtolower($cuenta) ?>?from=613"><?= sprintf(_("Arqueo %s"), htmlspecialchars($cuenta, ENT_QUOTES)) ?></a>
        · <button type="button" id="btn-imprimir"><?= _("Imprimir") ?></button>
        · <button type="button" id="btn-pdf"><?= _("Descargar PDF") ?></button>
    </p>

    <article class="informe-613" id="informe-613">
        <header class="informe-613-cab">
            <div class="informe-613-meta">
                <div>ctr: <span id="ctr-nombre"></span></div>
                <div><?= _("fecha cierre:") ?> <span id="fecha-cierre"></span></div>
            </div>
        </header>

        <table class="informe-613-tabla">
            <colgroup>
                <col class="col-desc">
                <col class="col-sep">
                <col class="col-num">
                <col class="col-sep">
                <col class="col-num">
                <col class="col-sep">
                <col class="col-pct">
            </colgroup>
            <thead>
                <tr class="informe-613-cols">
                    <th></th>
                    <th class="sep" aria-hidden="true"></th>
                    <th class="num"><span><?= _("Previsto") ?></span></th>
                    <th class="sep" aria-hidden="true"></th>
                    <th class="num"><span><?= _("Realizado") ?></span></th>
                    <th class="sep" aria-hidden="true"></th>
                    <th class="num pct"><span>%</span></th>
                </tr>
            </thead>
            <tbody id="informe-613-body"></tbody>
        </table>

        <?php if ($cuenta === 'G'): ?>
        <section class="informe-613-resumen-g" id="informe-613-resumen-g">
            <div class="informe-613-resumen-fila">
                <span><?= _("Número de personas") ?></span>
                <span class="num" id="rg-personas"></span>
            </div>
            <div class="informe-613-resumen-fila">
                <span><?= _("Gasto acumulado de vivienda por persona y mes") ?></span>
                <span class="num" id="rg-gasto-viv"></span>
            </div>
            <div class="informe-613-resumen-fila informe-613-resumen-divide">
                <span><?= _("Media diaria de cocina (mes)") ?></span>
                <input type="text" name="media_cocina_mes" id="rg-cocina-mes"
                       class="num informe-613-cocina-input" aria-label="<?= htmlspecialchars(_("Media diaria de cocina (mes)"), ENT_QUOTES) ?>">
            </div>
            <div class="informe-613-resumen-fila">
                <span><?= _("Media diaria de cocina (acumulada)") ?></span>
                <input type="text" name="media_cocina_acum" id="rg-cocina-acum"
                       class="num informe-613-cocina-input" aria-label="<?= htmlspecialchars(_("Media diaria de cocina (acumulada)"), ENT_QUOTES) ?>">
            </div>
            <div class="informe-613-resumen-fila informe-613-resumen-divide informe-613-resumen-arqueo-total">
                <span class="informe-613-resumen-negrita"><?= _("Arqueo a fin de mes") ?></span>
                <span class="num" id="rg-arqueo"></span>
            </div>
            <div class="informe-613-resumen-fila informe-613-resumen-arqueo">
                <span><?= _("Saldo contable en Caja") ?></span>
                <span class="num" id="rg-caja"></span>
                <span><?= _("Saldo contable en Banco") ?></span>
                <span class="num" id="rg-banco"></span>
            </div>
            <div class="informe-613-resumen-fila informe-613-resumen-arqueo">
                <span><?= _("Dinero y vales de Caja") ?></span>
                <input type="text" name="dinero_arqueo_caja" id="rg-dinero-caja"
                       class="num informe-613-cocina-input" aria-label="<?= htmlspecialchars(_("Dinero y vales de Caja"), ENT_QUOTES) ?>">
                <span><?= _("Dinero en Banco") ?></span>
                <input type="text" name="dinero_arqueo_banco" id="rg-dinero-banco"
                       class="num informe-613-cocina-input" aria-label="<?= htmlspecialchars(_("Dinero en Banco"), ENT_QUOTES) ?>">
            </div>
        </section>
        <?php endif; ?>

        <section class="informe-613-obs">
            <h2><?= _("Observaciones") ?></h2>
            <textarea name="observaciones" id="obs-print" rows="3"
                      class="informe-613-obs-caja" aria-label="<?= htmlspecialchars(_("Observaciones"), ENT_QUOTES) ?>"></textarea>
        </section>

        <div class="informe-613-pie">
            <div class="informe-613-firmas">
                <div class="informe-613-firma informe-613-firma-d"><?= _("VºBº El d") ?></div>
                <div class="informe-613-firma informe-613-firma-scl"><?= _("VºBº El scl") ?></div>
            </div>
            <div class="informe-613-pie-meta">
                <span id="codigo-informe">613 <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></span>
                <span id="fecha-impresion"></span>
            </div>
        </div>
    </article>
</div>
<script>window.CUENTA_613 = <?= json_encode($cuenta) ?>;</script>
<script src="/js/html2pdf.bundle.min.js"></script>
<script src="/js/resumen613.js?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/js/resumen613.js') ?: 0) ?>"></script>
