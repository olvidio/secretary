<section class="yo-cierre">
    <h1><?= _("Remanente") ?></h1>
    <p class="muted"><?= _("Cantidad que se queda en el banco o en la cuenta personal y no se envía al centro. En la remesa se manda el disponible: el saldo de caja y banco menos este remanente.") ?></p>
    <form id="form-remanente" class="grid-form">
        <label><?= _("Cantidad que se queda") ?>
            <input name="remanente" id="yo-remanente" inputmode="decimal" autocomplete="off">
        </label>
        <button type="submit"><?= _("Guardar") ?></button>
        <p class="ok" id="msg-remanente" hidden><?= _("Guardado") ?></p>
        <p class="error" id="err-remanente" hidden></p>
    </form>
    <p class="muted"><?= _("Cero significa enviar todo el saldo. El remanente es fijo: no cambia solo cada mes.") ?></p>
</section>
