<h1><?= _("Disponible") ?></h1>
<p class="muted"><?= _("Saldo operativo de cada persona para aplicar a labores apostólicas (partidas 7). No es la cuenta corriente contable: se puede ajustar a mano y, al aceptar una remesa, se puede sustituir por el disponible que envía la persona (saldo de su cuenta menos el remanente).") ?></p>
<p id="disp-err" class="error" hidden></p>
<p id="disp-ok" class="ok" hidden></p>
<table id="tabla-disponible">
    <thead>
    <tr>
        <th><?= _("Persona") ?></th>
        <th><?= _("Desgrava") ?></th>
        <th class="num"><?= _("Disponible") ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<p>
    <button type="button" id="btn-proponer"><?= _("Proponer destinos 7") ?></button>
</p>
<section id="propuesta" hidden>
    <h2><?= _("Propuesta") ?></h2>
    <p id="propuesta-meta" class="muted"></p>
    <table id="tabla-propuesta">
        <thead>
        <tr><th><?= _("Persona") ?></th><th><?= _("Texto") ?></th><th class="num"><?= _("Importes") ?></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <p>
        <button type="button" id="btn-confirmar"><?= _("Confirmar instrucciones") ?></button>
    </p>
</section>
<section id="instrucciones-pendientes">
    <h2><?= _("Instrucciones pendientes de remesa") ?></h2>
    <p class="muted"><?= _("Lo que la persona debe ingresar en su libro y enviar en una remesa. No hay asiento en P del centro hasta aceptar esa remesa. Puede retirar la instrucción si ya no aplica (devuelve el disponible).") ?></p>
    <p id="instr-vacio" class="muted" hidden><?= _("No hay instrucciones abiertas.") ?></p>
    <table id="tabla-instrucciones" hidden>
        <thead>
        <tr><th><?= _("Persona") ?></th><th><?= _("Texto") ?></th><th></th></tr>
        </thead>
        <tbody></tbody>
    </table>
</section>
<script src="/js/disponible.js"></script>
