<div id="yo-modal" class="yo-modal" hidden>
    <form id="yo-form" class="yo-sheet">
        <div class="yo-sheet-head">
            <h2 id="yo-form-titulo"><?= _("Movimiento") ?></h2>
            <button type="button" id="yo-pill-generales" class="yo-pill" hidden aria-pressed="false"><?= _("Personal") ?></button>
        </div>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
        <input type="hidden" name="asiento_id" value="">
        <label class="yo-importe-grande"><?= _("Importe") ?>
            <input name="cantidad" inputmode="decimal" required placeholder="0,00" autofocus>
        </label>
        <div id="yo-cats-grid" class="yo-cats-grid"></div>
        <div id="yo-plantillas-wrap" class="yo-plantillas-wrap" hidden>
            <p class="yo-plantillas-label muted"><?= _("Plantillas del centro") ?></p>
            <div id="yo-plantillas-grid" class="yo-cats-grid yo-plantillas-grid"></div>
        </div>
        <input type="hidden" name="cuenta_id" required>
        <input type="hidden" name="plantilla_id" value="">
        <input type="hidden" name="sentido" value="gasto">
        <input type="hidden" name="gasto_generales" value="0">
        <div id="yo-generales-campos" class="yo-generales-campos">
            <label><?= _("Concepto en generales (G)") ?>
                <select name="concepto_generales">
                    <option value=""><?= _("— Elija —") ?></option>
                </select>
            </label>
            <p class="muted"><?= _("Al aceptar la remesa, el centro cargará vivienda general (P/21), ingreso G/11 y este gasto en generales. La vivienda personal (P/212) es otra categoría, como ordinarios.") ?></p>
        </div>
        <fieldset class="yo-tesoreria">
            <label><span><?= _("Caja") ?></span><input type="radio" name="tesoreria" value="CAJA" checked></label>
            <label><span><?= _("Banco") ?></span><input type="radio" name="tesoreria" value="BANCO"></label>
        </fieldset>
        <div id="yo-traspaso-campos" class="yo-traspaso-campos">
            <label><span><?= _("Origen") ?></span>
                <select name="tesoreria_origen">
                    <option value="CAJA"><?= _("Caja") ?></option>
                    <option value="BANCO"><?= _("Banco") ?></option>
                </select>
            </label>
            <label><span><?= _("Destino") ?></span>
                <select name="tesoreria_destino">
                    <option value="BANCO"><?= _("Banco") ?></option>
                    <option value="CAJA"><?= _("Caja") ?></option>
                </select>
            </label>
        </div>
        <label><?= _("Fecha") ?> <input name="fecha" type="date" required></label>
        <details id="yo-imputacion-wrap">
            <summary><?= _("Fecha de imputación (opcional)") ?></summary>
            <label><?= _("Imputar en") ?> <input name="fecha_imputacion" type="date"></label>
        </details>
        <label><?= _("Nota") ?> <input name="nota" maxlength="200"></label>
        <p id="yo-form-err" class="error" hidden></p>
        <div class="yo-sheet-actions">
            <button type="button" id="yo-form-cancelar"><?= _("Cancelar") ?></button>
            <button type="submit"><?= _("Guardar") ?></button>
        </div>
    </form>
</div>

<div id="yo-modal-desdoblar" class="yo-modal" hidden>
    <form id="yo-desdoblar-form" class="yo-sheet">
        <h2><?= _("Desdoblar") ?></h2>
        <p class="muted" id="yo-desdoblar-resumen"></p>
        <input type="hidden" name="asiento_id" value="">
        <fieldset class="yo-desdoblar-parte">
            <legend><?= _("Parte 1") ?></legend>
            <label><?= _("Importe") ?> <input name="cantidad1" inputmode="decimal" required placeholder="0,00"></label>
            <label><?= _("Categoría") ?> <select name="cuenta_id1" required></select></label>
            <label><?= _("Nota") ?> <input name="nota1" maxlength="200"></label>
        </fieldset>
        <fieldset class="yo-desdoblar-parte">
            <legend><?= _("Parte 2") ?></legend>
            <label><?= _("Importe") ?> <input name="cantidad2" inputmode="decimal" required placeholder="0,00"></label>
            <label><?= _("Categoría") ?> <select name="cuenta_id2" required></select></label>
            <label><?= _("Nota") ?> <input name="nota2" maxlength="200"></label>
        </fieldset>
        <p class="muted" id="yo-desdoblar-resto"></p>
        <p id="yo-desdoblar-err" class="error" hidden></p>
        <div class="yo-sheet-actions">
            <button type="button" id="yo-desdoblar-cancelar"><?= _("Cancelar") ?></button>
            <button type="submit"><?= _("Desdoblar") ?></button>
        </div>
    </form>
</div>
