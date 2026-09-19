<h1><?= _("Enviar a DL") ?></h1>
<p class="muted"><?= _("Registra un envío de efectivo a la delegación local como gasto de necesidades generales (P/71 desde caja). El reparto usa el saldo en caja de cada persona (apuntes C en Saldos): solo entran quienes tienen saldo positivo, proporcional a ese saldo. Importes en euros enteros.") ?></p>
<p id="envio-dl-err" class="error" hidden></p>
<p id="envio-dl-ok" class="ok" hidden></p>
<form id="form-envio-dl" class="grid-form">
    <label><?= _("Importe a enviar (€)") ?>
        <input id="inp-importe" required inputmode="decimal" placeholder="0,00">
    </label>
    <p>
        <button type="submit" id="btn-proponer"><?= _("Proponer") ?></button>
    </p>
</form>
<section id="propuesta-envio-dl" hidden>
    <h2><?= _("Propuesta") ?></h2>
    <p id="propuesta-envio-meta" class="muted"></p>
    <table id="tabla-propuesta-envio-dl">
        <thead>
        <tr><th><?= _("Persona") ?></th><th class="num"><?= _("Saldo caja") ?></th><th class="num"><?= _("Importe") ?></th><th><?= _("Movimiento") ?></th></tr>
        </thead>
        <tbody></tbody>
        <tfoot>
        <tr><th><?= _("Total") ?></th><th></th><th class="num" id="propuesta-total"></th><th></th></tr>
        </tfoot>
    </table>
    <p>
        <button type="button" id="btn-confirmar-envio-dl"><?= _("Confirmar y apuntar") ?></button>
    </p>
</section>
<script src="/js/envio_dl.js"></script>
