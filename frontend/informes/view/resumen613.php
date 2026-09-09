<?php $cuenta = $cuentaInforme ?? 'P'; ?>
<div class="informe-613-wrap">
    <h1 class="print-hide">Resumen mensual 613 <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></h1>
    <p class="print-hide informe-613-acciones">
        <a href="/arqueo-<?= strtolower($cuenta) ?>">Arqueo <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></a>
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
                <col class="col-num">
                <col class="col-num">
                <col class="col-num">
            </colgroup>
            <thead>
                <tr class="informe-613-cols">
                    <th></th>
                    <th class="num"><span>Previsto</span></th>
                    <th class="num"><span>Realizado</span></th>
                    <th class="num"><span>%</span></th>
                </tr>
            </thead>
            <tbody id="informe-613-body"></tbody>
        </table>

        <section class="informe-613-obs">
            <h2>Observaciones</h2>
            <div class="informe-613-obs-caja" id="obs-print"></div>
        </section>

        <footer class="informe-613-pie">
            <div class="informe-613-firmas">
                <div>VºBº El d</div>
                <div>El scl</div>
            </div>
            <div class="informe-613-pie-meta">
                <span id="codigo-informe">613 <?= htmlspecialchars($cuenta, ENT_QUOTES) ?></span>
                <span id="fecha-impresion"></span>
            </div>
        </footer>
    </article>

    <section id="extra" class="print-hide"></section>

    <form id="obs-form" class="print-hide informe-613-form">
        <label>Observaciones
            <textarea name="observaciones" rows="3"></textarea>
        </label>
        <?php if ($cuenta === 'P'): ?>
            <label>Saldo c/c personales (manual) <input name="saldo_cc_personales"></label>
        <?php else: ?>
            <label>Media cocina (mes) <input name="media_cocina_mes"></label>
            <label>Media cocina (acumulada) <input name="media_cocina_acum"></label>
        <?php endif; ?>
        <button type="submit">Guardar observaciones</button>
    </form>
</div>
<script>window.CUENTA_613 = <?= json_encode($cuenta) ?>;</script>
<script src="/js/html2pdf.bundle.min.js"></script>
<script src="/js/resumen613.js"></script>
