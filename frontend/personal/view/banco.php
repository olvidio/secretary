<div class="yo-con-ayuda">
    <h1><?= _("Banco") ?></h1>
    <details class="yo-ayuda">
        <summary aria-label="<?= htmlspecialchars(_("Ayuda"), ENT_QUOTES) ?>">i</summary>
        <div class="yo-ayuda-cuerpo">
            <p class="muted">
                <?= _("Sube el extracto del banco. Cada origen tiene un formato distinto. La siguiente vez solo se crean movimientos nuevos. Lo importado entra en Por categorizar hasta que le asignes una categoría.") ?>
            </p>
            <p class="muted"><?= _("N26: en la web, cuenta → Descargas → actividad de la cuenta → CSV.") ?></p>
            <p class="muted">
                <?= _("CaixaBank: CaixaBankNow → Cuentas → tu cuenta. Carga todo el periodo (pulsa «Ver más movimientos» si aparece) y elige Extraer movimientos / Descargar en Excel (.xls). También admite CSV si lo guardas así.") ?>
            </p>
            <p class="muted"><?= _("BBVA: Cuentas → la cuenta → Movimientos → Descargar en Excel o CSV.") ?></p>
            <p class="muted"><?= _("Banco Sabadell: Operativa diaria → Cuentas → Saldos y movimientos → Descargar → Excel o CSV.") ?></p>
        </div>
    </details>
</div>

<form id="yo-banco-form" class="yo-banco-form">
    <label><?= _("Banco") ?>
        <select name="banco" id="yo-banco-sel" required>
            <?php foreach (is_array($bancos ?? null) ? $bancos : [] as $banco): ?>
                <?php if (!is_array($banco)) { continue; } ?>
                <option value="<?= htmlspecialchars((string) ($banco['id'] ?? ''), ENT_QUOTES) ?>"><?= htmlspecialchars((string) ($banco['nombre'] ?? ''), ENT_QUOTES) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?= _("Fichero") ?>
        <input name="fichero" id="yo-banco-fichero" type="file" accept=".csv,.xls,.xlsx,text/csv" required>
    </label>
    <button type="submit" id="yo-banco-enviar"><?= _("Importar") ?></button>
</form>
<p class="ok" id="yo-banco-msg" hidden></p>
<p class="error" id="yo-banco-err" hidden></p>

<h2><?= _("Por categorizar") ?></h2>
<p class="muted" id="yo-banco-vacio" hidden><?= _("No hay movimientos pendientes.") ?></p>
<ul id="yo-banco-pend" class="yo-lista"></ul>

<div class="yo-con-ayuda">
    <h2><?= _("Otra contabilidad") ?></h2>
    <details class="yo-ayuda">
        <summary aria-label="<?= htmlspecialchars(_("Ayuda"), ENT_QUOTES) ?>">i</summary>
        <div class="yo-ayuda-cuerpo">
            <p class="muted">
                <?= _("No suman en ingresos y gastos del plan; sí mueven el banco. Puedes asignarles una categoría del plan más adelante.") ?>
            </p>
        </div>
    </details>
</div>
<p class="muted" id="yo-banco-otra-vacio" hidden><?= _("No hay movimientos en otra contabilidad.") ?></p>
<ul id="yo-banco-otra" class="yo-lista"></ul>
