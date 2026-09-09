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

<div id="yo-modal" class="yo-modal" hidden>
    <form id="yo-form" class="yo-sheet">
        <h2 id="yo-form-titulo">Movimiento</h2>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
        <label class="yo-importe-grande">Importe
            <input name="cantidad" inputmode="decimal" required placeholder="0,00" autofocus>
        </label>
        <div id="yo-cats-grid" class="yo-cats-grid"></div>
        <input type="hidden" name="cuenta_id" required>
        <input type="hidden" name="sentido" value="gasto">
        <fieldset class="yo-tesoreria">
            <legend>Desde / hacia</legend>
            <label><input type="radio" name="tesoreria" value="CAJA" checked> Caja</label>
            <label><input type="radio" name="tesoreria" value="BANCO"> Banco</label>
        </fieldset>
        <div id="yo-traspaso-campos" hidden>
            <label>Origen
                <select name="tesoreria_origen">
                    <option value="CAJA">Caja</option>
                    <option value="BANCO">Banco</option>
                </select>
            </label>
            <label>Destino
                <select name="tesoreria_destino">
                    <option value="BANCO">Banco</option>
                    <option value="CAJA">Caja</option>
                </select>
            </label>
        </div>
        <label>Fecha <input name="fecha" type="date" required></label>
        <details>
            <summary>Fecha de imputación (opcional)</summary>
            <label>Imputar en <input name="fecha_imputacion" type="date"></label>
        </details>
        <label>Nota <input name="nota" maxlength="200"></label>
        <p id="yo-form-err" class="error" hidden></p>
        <div class="yo-sheet-actions">
            <button type="button" id="yo-form-cancelar">Cancelar</button>
            <button type="submit">Guardar</button>
        </div>
    </form>
</div>
