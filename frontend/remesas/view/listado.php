<h1><?= _("Remesas") ?></h1>
<p class="muted"><?= _("Envíos mensuales de las personas. Aceptar genera un asiento en P contra la cuenta personal; rechazar no deja rastro. Reenviar el mismo mes sustituye la versión pendiente.") ?></p>
<form id="filtros-remesas" class="filters">
    <label><?= _("Estado") ?>
        <select name="estado">
            <option value=""><?= _("Todas") ?></option>
            <option value="enviada" selected><?= _("Recibidas") ?></option>
            <option value="aceptada"><?= _("Aceptadas") ?></option>
            <option value="rechazada"><?= _("Rechazadas") ?></option>
            <option value="sustituida"><?= _("Sustituidas") ?></option>
        </select>
    </label>
    <button type="submit"><?= _("Filtrar") ?></button>
</form>
<p id="remesas-err" class="error" hidden></p>
<table id="tabla-remesas">
    <thead>
    <tr>
        <th><?= _("Mes") ?></th>
        <th><?= _("Persona") ?></th>
        <th>V.</th>
        <th><?= _("Estado") ?></th>
        <th class="num"><?= _("Disponible") ?></th>
        <th><?= _("Enviada") ?></th>
        <th></th>
    </tr>
    </thead>
    <tbody></tbody>
</table>
<section id="remesa-detalle" hidden>
    <h2 id="remesa-detalle-titulo"><?= _("Detalle") ?></h2>
    <p id="remesa-detalle-meta" class="muted"></p>
    <p id="remesa-tesoreria" class="muted" hidden></p>
    <label id="remesa-sustituir-wrap" hidden>
        <input type="checkbox" id="remesa-sustituir"> <?= _("Sustituir el disponible por esa tesorería") ?>
    </label>
    <table id="tabla-remesa-lineas">
        <thead>
        <tr><th><?= _("Concepto") ?></th><th class="num"><?= _("Importe") ?></th><th><?= _("Detalle") ?></th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <div id="remesa-linea-detalle" class="remesa-linea-detalle" hidden>
        <h3 id="remesa-linea-detalle-titulo"><?= _("Desglose autorizado") ?></h3>
        <p class="muted"><?= _("Subcuentas del libro personal que suman la línea. Solo lectura; no crea cuentas en el centro.") ?></p>
        <table id="tabla-remesa-linea-detalle">
            <thead>
            <tr><th><?= _("Subcuenta") ?></th><th><?= _("Nombre") ?></th><th class="num"><?= _("Importe") ?></th><th><?= _("Generales / plantillas") ?></th></tr>
            </thead>
            <tbody></tbody>
        </table>
        <p id="remesa-linea-detalle-vacio" class="muted" hidden><?= _("Sin subcuentas en el desglose.") ?></p>
    </div>
    <div id="remesa-diff" hidden>
        <h3><?= _("Diferencia respecto a la versión anterior") ?></h3>
        <ul id="remesa-diff-list"></ul>
    </div>
    <p id="remesa-acciones">
        <button type="button" id="remesa-aceptar"><?= _("Aceptar") ?></button>
        <button type="button" id="remesa-rechazar"><?= _("Rechazar") ?></button>
    </p>
    <label><?= _("Nota al rechazar") ?> <input id="remesa-nota" maxlength="200"></label>
</section>
<script src="/js/remesas.js"></script>
