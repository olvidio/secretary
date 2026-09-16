<h1>Enviar a DL</h1>
<p class="muted">Registra un envío de efectivo a la delegación local como gasto de necesidades generales (P/71 desde caja). Entran los nombres no exentos con saldo ≥ 0 (los negativos quedan fuera). Si alguien tiene saldo cero, el reparto es equitativo; si todos tienen saldo positivo, es proporcional al saldo. Importes en euros enteros.</p>
<p id="envio-dl-err" class="error" hidden></p>
<p id="envio-dl-ok" class="ok" hidden></p>
<form id="form-envio-dl" class="grid-form">
    <label>Importe a enviar (€)
        <input id="inp-importe" required inputmode="decimal" placeholder="0,00">
    </label>
    <p>
        <button type="submit" id="btn-proponer">Proponer</button>
    </p>
</form>
<section id="propuesta-envio-dl" hidden>
    <h2>Propuesta</h2>
    <p id="propuesta-envio-meta" class="muted"></p>
    <table id="tabla-propuesta-envio-dl">
        <thead>
        <tr><th>Persona</th><th class="num">Saldo</th><th class="num">Importe</th><th>Movimiento</th></tr>
        </thead>
        <tbody></tbody>
        <tfoot>
        <tr><th>Total</th><th></th><th class="num" id="propuesta-total"></th><th></th></tr>
        </tfoot>
    </table>
    <p>
        <button type="button" id="btn-confirmar-envio-dl">Confirmar y apuntar</button>
    </p>
</section>
<script src="/js/envio_dl.js"></script>
