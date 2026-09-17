<h1><?= _("Disponible") ?></h1>
<p class="muted"><?= _("Saldo operativo de cada persona para aplicar a labores apostólicas (partidas 7). No es la cuenta corriente contable: se puede ajustar a mano y, al aceptar una remesa, se puede sustituir por el saldo de tesorería que envía la persona.") ?></p>
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
        <button type="button" id="btn-confirmar"><?= _("Confirmar y apuntar") ?></button>
    </p>
</section>
<script src="/js/disponible.js"></script>
