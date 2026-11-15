<?php
/**
 * «?» fijo abajo a la derecha: lleva a la ayuda desde cualquier pantalla del centro.
 * No va en el ámbito personal (`layout_yo.php`): en el móvil estorba.
 */
if (($nav ?? '') === 'ayuda') {
    return;
}
?>
<a href="/ayuda" class="ayuda-flotante" aria-label="<?= htmlspecialchars(_("Ayuda"), ENT_QUOTES) ?>" title="<?= htmlspecialchars(_("Ayuda"), ENT_QUOTES) ?>">?</a>
