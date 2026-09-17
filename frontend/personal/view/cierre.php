<div class="yo-month">
    <button type="button" id="yo-mes-ant" aria-label="<?= htmlspecialchars(_("Mes anterior"), ENT_QUOTES) ?>">‹</button>
    <h1 id="yo-mes-titulo"><?= _("Mes") ?></h1>
    <button type="button" id="yo-mes-sig" aria-label="<?= htmlspecialchars(_("Mes siguiente"), ENT_QUOTES) ?>">›</button>
</div>

<section class="yo-cierre">
    <h2><?= _("Por defecto") ?></h2>
    <p class="muted"><?= _("Día del mes en que cierra el periodo. Vacío = último día del mes. Ejemplo habitual: 25.") ?></p>
    <form id="form-cierre-defecto" class="grid-form">
        <label><?= _("Día del mes") ?>
            <input name="dia_cierre" type="number" min="1" max="28" placeholder="25">
        </label>
        <label class="cuenta-tipo-op">
            <input type="checkbox" name="dia_habil" value="1">
            <?= _("Si cae en sábado o domingo, usar el lunes siguiente") ?>
        </label>
        <button type="submit"><?= _("Guardar regla") ?></button>
        <p class="ok" id="msg-defecto" hidden><?= _("Guardado") ?></p>
    </form>

    <h2><?= _("Este mes") ?></h2>
    <p class="muted" id="cierre-calculado"></p>
    <form id="form-cierre-mes" class="grid-form">
        <label><?= _("Fecha concreta") ?> <input name="fecha_cierre" type="date" required></label>
        <button type="submit"><?= _("Fijar para este mes") ?></button>
        <button type="button" id="btn-cierre-borrar" class="peligro"><?= _("Quitar fecha concreta") ?></button>
        <p class="ok" id="msg-mes" hidden><?= _("Guardado") ?></p>
    </form>
</section>
