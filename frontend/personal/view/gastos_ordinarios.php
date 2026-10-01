<div class="yo-month print-hide">
    <button type="button" id="yo-mes-ant" aria-label="<?= htmlspecialchars(_("Mes anterior"), ENT_QUOTES) ?>">‹</button>
    <h1 id="yo-mes-titulo"><?= _("Mes") ?></h1>
    <button type="button" id="yo-mes-sig" aria-label="<?= htmlspecialchars(_("Mes siguiente"), ENT_QUOTES) ?>">›</button>
</div>

<article class="go-hoja">
    <header class="go-cabecera">
        <h1 id="go-nombre"></h1>
        <p id="go-periodo"></p>
    </header>
    <p id="go-vacia" class="muted" hidden><?= _("No hay gastos ordinarios en este periodo.") ?></p>
    <table id="go-tabla" class="go-tabla" hidden>
        <thead>
            <tr>
                <th><?= _("Fecha") ?></th>
                <th><?= _("Concepto") ?></th>
                <th><?= _("Cuenta") ?></th>
                <th class="num"><?= _("Importe") ?></th>
            </tr>
        </thead>
        <tbody id="go-lineas"></tbody>
        <tfoot>
            <tr>
                <td colspan="3"><?= _("Total") ?></td>
                <td class="num" id="go-total"></td>
            </tr>
        </tfoot>
    </table>
    <p class="print-hide"><button type="button" onclick="window.print()"><?= _("Imprimir") ?></button></p>
</article>
