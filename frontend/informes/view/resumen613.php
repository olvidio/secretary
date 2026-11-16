<?php
$cuenta = $cuentaInforme ?? 'P';
$codigo613 = ($cuenta === 'G' && !empty($esCentroSg)) ? 'G-D' : $cuenta;
?>
<div class="informe-613-wrap">
    <h1 class="print-hide"><?= sprintf(_("Resumen mensual 613 %s"), htmlspecialchars($codigo613, ENT_QUOTES)) ?></h1>
    <p class="print-hide informe-613-ayuda"><?= _("Las celdas azules admiten valores introducidos manualmente; se guardan al salir del campo.") ?></p>
    <p class="print-hide informe-613-acciones">
        <a href="/arqueo-p?from=613&amp;libro=<?= strtolower($cuenta) ?>"><?= _("Arqueo Caja") ?></a><?php if ($cuenta === 'G' && empty($esCentroSg)): ?>
        · <a href="/arqueo-g?from=613&amp;libro=g"><?= _("Saldo banco") ?></a><?php endif; ?>
        · <button type="button" id="btn-imprimir"><?= _("Imprimir") ?></button>
        · <button type="button" id="btn-pdf"><?= _("Descargar PDF") ?></button>
    </p>

    <section class="aviso-cierre print-hide avisos-613" id="avisos-613" hidden aria-live="polite">
        <p id="avisos-613-espera" class="avisos-613-espera" hidden><span class="avisos-613-rueda" aria-hidden="true"></span><?= _("Haciendo comprobaciones, espera un momento…") ?></p>
        <div id="avisos-613-contenido" hidden>
            <p><strong><?= _("Antes de enviar el resumen, conviene resolver esto:") ?></strong></p>
            <ul id="avisos-613-lista"></ul>
            <p class="muted"><?= _("Las cifras se pueden ver igualmente, pero pueden no ser las definitivas.") ?></p>
        </div>
        <div id="avisos-613-notas" class="avisos-613-notas" hidden>
            <p><strong><?= _("Para tener en cuenta (no es un error):") ?></strong> <?= _("estos movimientos se cuentan en una fecha y su contrapartida va en otra, a distinto lado de la fecha de cierre. Por eso el saldo final o las cuentas personales pueden no ser los esperados.") ?></p>
            <ul id="avisos-613-notas-lista"></ul>
            <p><button type="button" id="btn-notas-obs"><?= _("Añadir a observaciones") ?></button> <span id="notas-obs-msg" class="ok" hidden><?= _("Añadido a las observaciones.") ?></span></p>
        </div>
    </section>

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

        <?php if ($cuenta === 'G' && !empty($esCentroSg)): ?>
        <p class="informe-613-nota">* <?= _("Supone 100% aportaciones (nº s × 12)") ?></p>
        <section class="informe-613-resumen-g informe-613-cierre-sg" id="informe-613-resumen-g">
            <div class="informe-613-vb-fila">
                <span><?= _("VºBº El d") ?></span>
                <span class="informe-613-vb-scl"><?= _("VºBº El scl") ?></span>
                <div class="informe-613-arqueo-bloque">
                    <span class="informe-613-resumen-negrita"><?= _("Arqueo fin de mes") ?></span>
                    <div class="informe-613-arqueo-dato">
                        <span><?= _("Saldo contable") ?></span>
                        <span class="num" id="rg-saldo-sg"></span>
                    </div>
                    <div class="informe-613-arqueo-dato">
                        <span><?= _("Dinero y vales") ?></span>
                        <input type="text" name="dinero_arqueo_caja" id="rg-dinero-caja"
                               class="num informe-613-cocina-input" aria-label="<?= htmlspecialchars(_("Dinero y vales"), ENT_QUOTES) ?>">
                    </div>
                </div>
            </div>
        </section>
        <?php elseif ($cuenta === 'G'): ?>
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
            <?php if ($cuenta !== 'G' || empty($esCentroSg)): ?>
            <div class="informe-613-firmas">
                <div class="informe-613-firma informe-613-firma-d"><?= _("VºBº El d") ?></div>
                <div class="informe-613-firma informe-613-firma-scl"><?= _("VºBº El scl") ?></div>
            </div>
            <?php endif; ?>
            <div class="informe-613-pie-meta">
                <span id="codigo-informe">613 <?= htmlspecialchars($codigo613, ENT_QUOTES) ?></span>
                <span id="fecha-impresion"></span>
            </div>
        </div>
    </article>
</div>
<script>window.CUENTA_613 = <?= json_encode($cuenta) ?>;
window.I18N_AVISOS_613 = {
  destinos: {
    saldos: <?= json_encode(_("Ir a Saldos"), JSON_UNESCAPED_UNICODE) ?>,
    comprobaciones: <?= json_encode(_("Ir a Comprobaciones"), JSON_UNESCAPED_UNICODE) ?>,
    cierre: <?= json_encode(_("Ir a Cierre de mes"), JSON_UNESCAPED_UNICODE) ?>,
    apuntes: <?= json_encode(_("Ir a Apuntes"), JSON_UNESCAPED_UNICODE) ?>,
  },
  confirmar: <?= json_encode(_("Quedan comprobaciones pendientes (ver el recuadro de arriba). ¿Imprimir igualmente?"), JSON_UNESCAPED_UNICODE) ?>,
  confirmarComprobando: <?= json_encode(_("Las comprobaciones aún no han terminado. ¿Imprimir igualmente?"), JSON_UNESCAPED_UNICODE) ?>,
};</script>
<?php
$publicJs = dirname(__DIR__, 3) . '/public/js';
if ($cuenta === 'P'): ?>
<script src="/js/bloques613p.js?v=<?= (int) (@filemtime($publicJs . '/bloques613p.js') ?: 0) ?>"></script>
<?php else: ?>
<script src="/js/bloques613g.js?v=<?= (int) (@filemtime($publicJs . '/bloques613g.js') ?: 0) ?>"></script>
<?php endif; ?>
<script src="/js/html2pdf.bundle.min.js"></script>
<script src="/js/resumen613.js?v=<?= (int) (@filemtime(dirname(__DIR__, 3) . '/public/js/resumen613.js') ?: 0) ?>"></script>
