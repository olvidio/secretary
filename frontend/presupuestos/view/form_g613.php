<?php $esCentroSg = !empty($esCentroSg); ?>
<div class="informe-613-wrap presupuesto-613-wrap">
    <h1 class="print-hide"><?= _("Presupuesto G") ?></h1>
    <p class="print-hide informe-613-ayuda"><?= _("Las celdas azules son editables (previsto anual). El 613 prorratea × meses / 12.") ?></p>
    <?php if ($esCentroSg): ?>
    <p class="print-hide muted"><?= _("Los destinos del 42 al 54 se nombran en") ?>
        <a href="/conceptos-g"><?= _("Conceptos") ?></a><?= _(", en Plan y ejercicio.") ?></p>
    <?php endif; ?>
    <p class="print-hide informe-613-acciones">
        <button type="button" id="btn-imprimir-presu-g"><?= _("Imprimir") ?></button>
    </p>
    <p class="filters print-hide">
        <label><?= _("Año") ?>
            <select id="sel-anio-presupuesto" disabled></select>
        </label>
    </p>

    <article class="informe-613" id="informe-presupuesto-g">
        <header class="informe-613-cab">
            <div class="informe-613-meta">
                <div>ctr: <span id="presu-ctr-nombre"></span></div>
                <div><?= _("ejercicio:") ?> <span id="presu-ejercicio"></span></div>
            </div>
        </header>

        <form id="form-presu-g">
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
                        <th class="num"><span><?= _("Previsto anual") ?></span></th>
                        <th class="sep" aria-hidden="true"></th>
                        <th class="num"><span><?= _("Realizado") ?></span></th>
                        <th class="sep" aria-hidden="true"></th>
                        <th class="num pct"><span>%</span></th>
                    </tr>
                </thead>
                <tbody id="presupuesto-613-body"></tbody>
            </table>
            <p class="print-hide presupuesto-613-guardar">
                <button type="submit"><?= _("Guardar") ?></button>
                <span id="msg-presu-g" class="ok" hidden><?= _("Guardado") ?></span>
            </p>
        </form>

        <div class="informe-613-pie">
            <div class="informe-613-pie-meta">
                <span id="presu-pie-etiqueta"><?= _("Presupuesto G") ?></span>
            </div>
        </div>
    </article>
</div>
<?php
$publicRoot = dirname(__DIR__, 3) . '/public';
?>
<script>
window.I18N_PRESU_G = {
  pieG: <?= json_encode(_("Presupuesto G"), JSON_UNESCAPED_UNICODE) ?>,
  pieGd: <?= json_encode(_("Presupuesto G-D"), JSON_UNESCAPED_UNICODE) ?>,
};
</script>
<script src="/js/bloques613g.js?v=<?= (int) (@filemtime($publicRoot . '/js/bloques613g.js') ?: 0) ?>"></script>
<script src="/js/presupuesto613g.js?v=<?= (int) (@filemtime($publicRoot . '/js/presupuesto613g.js') ?: 0) ?>"></script>
