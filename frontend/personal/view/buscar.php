<h1><?= _("Buscar movimientos") ?></h1>
<form id="yo-buscar-form" class="yo-buscar-form">
    <div class="yo-buscar-rapido" role="group" aria-label="<?= htmlspecialchars(_("Periodo"), ENT_QUOTES) ?>">
        <button type="button" data-periodo="anio"><?= _("Este año") ?></button>
        <button type="button" data-periodo="anio-ant"><?= _("Año pasado") ?></button>
        <button type="button" data-periodo="12m"><?= _("Últimos 12 meses") ?></button>
        <button type="button" data-periodo="mes"><?= _("Este mes") ?></button>
    </div>
    <div class="yo-buscar-fila">
        <label><?= _("Desde") ?> <input type="date" name="desde"></label>
        <label><?= _("Hasta") ?> <input type="date" name="hasta"></label>
    </div>
    <div class="yo-buscar-fila">
        <label><?= _("Tipo") ?>
            <select name="sentido">
                <option value=""><?= _("Todos") ?></option>
                <option value="gasto"><?= _("Gastos") ?></option>
                <option value="ingreso"><?= _("Ingresos") ?></option>
                <option value="traspaso"><?= _("Traspasos") ?></option>
            </select>
        </label>
        <label><?= _("Caja / banco") ?>
            <select name="tesoreria" id="yo-buscar-tesoreria">
                <option value=""><?= _("Todos") ?></option>
            </select>
        </label>
    </div>
    <label><?= _("Categoría") ?>
        <select name="categoria_id" id="yo-buscar-categoria">
            <option value=""><?= _("Todas") ?></option>
        </select>
    </label>
    <label><?= _("Texto en la nota o la categoría") ?>
        <input type="search" name="texto" autocomplete="off" placeholder="<?= htmlspecialchars(_("p. ej. farmacia"), ENT_QUOTES) ?>">
    </label>
    <div class="yo-buscar-fila">
        <label><?= _("Importe mínimo") ?> <input type="text" name="importe_min" inputmode="decimal" autocomplete="off"></label>
        <label><?= _("Importe máximo") ?> <input type="text" name="importe_max" inputmode="decimal" autocomplete="off"></label>
    </div>
    <div class="yo-buscar-acciones">
        <button type="submit"><?= _("Buscar") ?></button>
        <button type="button" id="yo-buscar-limpiar"><?= _("Limpiar") ?></button>
    </div>
</form>

<section id="yo-buscar-resultado" hidden>
    <p class="muted" id="yo-buscar-resumen"></p>
    <div class="yo-flujo yo-buscar-totales">
        <div class="ing"><span><?= _("Ingresos") ?></span><strong id="yo-buscar-ing">0,00</strong></div>
        <div class="gas"><span><?= _("Gastos") ?></span><strong id="yo-buscar-gas">0,00</strong></div>
    </div>
    <p id="yo-lista-vacia" class="muted" hidden><?= _("Ningún movimiento cumple el filtro.") ?></p>
    <ul id="yo-lista" class="yo-lista"></ul>
</section>
<?php include __DIR__ . '/_form_movimiento.php'; ?>
