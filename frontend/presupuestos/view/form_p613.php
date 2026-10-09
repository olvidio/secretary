<div class="informe-613-wrap presupuesto-613-wrap">
    <h1 class="print-hide"><?= _("Presupuesto P") ?></h1>
    <p class="print-hide informe-613-ayuda"><?= _("Las celdas azules son editables (previsto anual). El 613 prorratea × meses / 12.") ?></p>
    <p class="print-hide informe-613-acciones">
        <a href="/prevision-personal"><?= _("Previsión personal") ?></a>
        · <a href="/prevision"><?= _("Previsión") ?></a>
        · <button type="button" id="btn-imprimir-presu-p"><?= _("Imprimir") ?></button>
    </p>
    <p class="filters print-hide">
        <label><?= _("Año") ?>
            <select id="sel-anio-presupuesto" disabled></select>
        </label>
    </p>

    <article class="informe-613" id="informe-presupuesto-p">
        <header class="informe-613-cab">
            <div class="informe-613-meta">
                <div>ctr: <span id="presu-ctr-nombre"></span></div>
                <div><?= _("ejercicio:") ?> <span id="presu-ejercicio"></span></div>
            </div>
        </header>

        <form id="form-presu-p">
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
                <span id="msg-presu-p" class="ok" hidden><?= _("Guardado") ?></span>
            </p>
        </form>

        <div class="informe-613-pie">
            <div class="informe-613-pie-meta">
                <span><?= _("Presupuesto P") ?></span>
            </div>
        </div>
    </article>
</div>
<?php
$publicRoot = dirname(__DIR__, 3) . '/public';
?>
<script src="/js/bloques613p.js?v=<?= (int) (@filemtime($publicRoot . '/js/bloques613p.js') ?: 0) ?>"></script>
<script src="/js/presupuesto613p.js?v=<?= (int) (@filemtime($publicRoot . '/js/presupuesto613p.js') ?: 0) ?>"></script>
