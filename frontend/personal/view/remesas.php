<div class="yo-month">
    <button type="button" id="yo-mes-ant" aria-label="<?= htmlspecialchars(_("Mes anterior"), ENT_QUOTES) ?>">‹</button>
    <h1 id="yo-mes-titulo"><?= _("Mes") ?></h1>
    <button type="button" id="yo-mes-sig" aria-label="<?= htmlspecialchars(_("Mes siguiente"), ENT_QUOTES) ?>">›</button>
</div>
<p class="muted"><?= _("Envío mensual al centro. La caja y el banco propios no viajan como movimientos. Se envía el disponible (saldo menos el remanente), para que el centro pueda actualizar el suyo.") ?></p>
<p id="yo-remesa-msg" class="ok" hidden></p>
<p id="yo-remesa-err" class="error" hidden></p>
<section class="yo-remesa-resumen">
    <p id="yo-remesa-estado" class="muted"><?= _("Cargando…") ?></p>
    <ul id="yo-remesa-lineas" class="yo-lista"></ul>
    <p id="yo-remesa-vacia" class="muted" hidden><?= _("No hay ingresos ni gastos en este mes (sí se puede enviar para anular una remesa ya aceptada).") ?></p>
</section>
<p>
    <label><?= _("Nota para el centro") ?> <input id="yo-remesa-nota" maxlength="200"></label>
</p>
<p>
    <label><?= _("Saldo de tu cuenta (caja+banco a la fecha de cierre)") ?>
        <input id="yo-remesa-tesoreria" inputmode="decimal">
    </label>
</p>
<p><?= _("Remanente que se queda") ?>: <strong id="yo-remesa-remanente">0,00</strong>
    <a href="/yo/remanente"><?= _("Cambiar") ?></a></p>
<p><?= _("Neto del mes") ?>: <strong id="yo-remesa-neto">0,00</strong>
    <span class="muted" id="yo-remesa-periodo"></span></p>
<p><?= _("Disponible que se envía") ?>: <strong id="yo-remesa-disponible">0,00</strong></p>
<p id="yo-asig" class="ok" hidden></p>
<p>
    <button type="button" id="yo-remesa-enviar"><?= _("Cerrar y enviar mes") ?></button>
</p>
<section>
    <h2><?= _("Historial de este mes") ?></h2>
    <ul id="yo-remesa-hist" class="yo-lista"></ul>
</section>
<section>
    <h2><?= _("Solicitudes de detalle") ?></h2>
    <ul id="yo-remesa-sols" class="yo-lista"></ul>
    <p id="yo-remesa-sols-vacia" class="muted"><?= _("No hay solicitudes pendientes.") ?></p>
</section>
