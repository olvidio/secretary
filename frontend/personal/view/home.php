<div class="yo-month">
    <button type="button" id="yo-mes-ant" aria-label="Mes anterior">‹</button>
    <h1 id="yo-mes-titulo">Mes</h1>
    <button type="button" id="yo-mes-sig" aria-label="Mes siguiente">›</button>
</div>
<section class="yo-hero">
    <div class="yo-chart-wrap">
        <div id="yo-chart" class="yo-chart" role="img" aria-label="Distribución del mes"></div>
        <div class="yo-chart-center">
            <span class="yo-chart-label">Saldo</span>
            <strong id="yo-saldo">0,00</strong>
        </div>
    </div>
    <div class="yo-saldos">
        <div><span>Caja</span><strong id="yo-caja">0,00</strong></div>
        <div><span>Banco</span><strong id="yo-banco">0,00</strong></div>
    </div>
    <div class="yo-flujo">
        <div class="ing"><span>Ingresos</span><strong id="yo-ingresos">0,00</strong></div>
        <div class="gas"><span>Gastos</span><strong id="yo-gastos">0,00</strong></div>
    </div>
</section>
<div class="yo-fabs">
    <button type="button" id="yo-btn-ingreso" class="yo-fab ing">+ Ingreso</button>
    <button type="button" id="yo-btn-gasto" class="yo-fab gas">− Gasto</button>
</div>
<p class="yo-traspaso-link"><button type="button" id="yo-btn-traspaso">Traspaso caja ↔ banco</button></p>
<ul id="yo-leyenda" class="yo-leyenda"></ul>

<?php include __DIR__ . '/_form_movimiento.php'; ?>
