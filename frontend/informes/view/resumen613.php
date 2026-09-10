<?php $cuenta = $cuentaInforme ?? 'P'; ?>
<div class="informe-613-wrap">
    <h1 class="print-hide">Resumen mensual 613 <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></h1>
    <p class="print-hide informe-613-ayuda">Las celdas azules admiten valores introducidos manualmente; se guardan al salir del campo.</p>
    <p class="print-hide informe-613-acciones">
        <a href="/arqueo-<?= strtolower($cuenta) ?>?from=613">Arqueo <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></a>
        · <button type="button" id="btn-imprimir">Imprimir</button>
        · <button type="button" id="btn-pdf">Descargar PDF</button>
    </p>

    <article class="informe-613" id="informe-613">
        <header class="informe-613-cab">
            <div class="informe-613-meta">
                <div>ctr: <span id="ctr-nombre"></span></div>
                <div>fecha cierre: <span id="fecha-cierre"></span></div>
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
                    <th class="num"><span>Previsto</span></th>
                    <th class="sep" aria-hidden="true"></th>
                    <th class="num"><span>Realizado</span></th>
                    <th class="sep" aria-hidden="true"></th>
                    <th class="num pct"><span>%</span></th>
                </tr>
            </thead>
            <tbody id="informe-613-body"></tbody>
        </table>

        <?php if ($cuenta === 'G'): ?>
        <section class="informe-613-resumen-g" id="informe-613-resumen-g">
            <div class="informe-613-resumen-fila">
                <span>Número de personas</span>
                <span class="num" id="rg-personas"></span>
            </div>
            <div class="informe-613-resumen-fila">
                <span>Gasto acumulado de vivienda por persona y mes</span>
                <span class="num" id="rg-gasto-viv"></span>
            </div>
            <div class="informe-613-resumen-fila informe-613-resumen-divide">
                <span>Media diaria de cocina (mes)</span>
                <input type="text" name="media_cocina_mes" id="rg-cocina-mes"
                       class="num informe-613-cocina-input" aria-label="Media diaria de cocina (mes)">
            </div>
            <div class="informe-613-resumen-fila">
                <span>Media diaria de cocina (acumulada)</span>
                <input type="text" name="media_cocina_acum" id="rg-cocina-acum"
                       class="num informe-613-cocina-input" aria-label="Media diaria de cocina (acumulada)">
            </div>
            <div class="informe-613-resumen-fila informe-613-resumen-divide informe-613-resumen-arqueo-total">
                <span class="informe-613-resumen-negrita">Arqueo a fin de mes</span>
                <span class="num" id="rg-arqueo"></span>
            </div>
            <div class="informe-613-resumen-fila informe-613-resumen-arqueo">
                <span>Saldo contable en Caja</span>
                <span class="num" id="rg-caja"></span>
                <span>Saldo contable en Banco</span>
                <span class="num" id="rg-banco"></span>
            </div>
            <div class="informe-613-resumen-fila informe-613-resumen-arqueo">
                <span>Dinero y vales de Caja</span>
                <input type="text" name="dinero_arqueo_caja" id="rg-dinero-caja"
                       class="num informe-613-cocina-input" aria-label="Dinero y vales de Caja">
                <span>Dinero en Banco</span>
                <input type="text" name="dinero_arqueo_banco" id="rg-dinero-banco"
                       class="num informe-613-cocina-input" aria-label="Dinero en Banco">
            </div>
        </section>
        <?php endif; ?>

        <section class="informe-613-obs">
            <h2>Observaciones</h2>
            <textarea name="observaciones" id="obs-print" rows="3"
                      class="informe-613-obs-caja" aria-label="Observaciones"></textarea>
        </section>

        <footer class="informe-613-pie">
            <div class="informe-613-firmas">
                <div class="informe-613-firma informe-613-firma-d">VºBº El d</div>
                <div class="informe-613-firma informe-613-firma-scl">VºBº El scl</div>
            </div>
            <div class="informe-613-pie-meta">
                <span id="codigo-informe">613 <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></span>
                <span id="fecha-impresion"></span>
            </div>
        </footer>
    </article>
</div>
<script>window.CUENTA_613 = <?= json_encode($cuenta) ?>;</script>
<script src="/js/html2pdf.bundle.min.js"></script>
<script src="/js/resumen613.js"></script>
