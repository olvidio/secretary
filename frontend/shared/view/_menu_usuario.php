<?php
$usuarioNombre = (string) ($usuario ?? '');
$navCuenta = (string) ($nav ?? '');
$esLibroPersonal = (($_SESSION['nivel'] ?? '') === 'persona');
$itemsCuenta = [
    ['mensajes', '/mensajes', _('Mensajes')],
];
if (!empty($mostrarMenuAmbito)) {
    $itemsCuenta[] = ['cuenta-ambito', '/cuenta/ambito', _('Ámbito')];
} elseif (!$esLibroPersonal) {
    $itemsCuenta[] = ['cuenta-centro', '/cuenta/centro', _('Centro')];
}
$itemsCuenta[] = ['cuenta-personal', '/cuenta/personal', _('Personal')];
if ($esLibroPersonal) {
    $itemsCuenta[] = ['yo-remanente', '/yo/remanente', _('Remanente')];
    if (!empty($mostrarMenuPersonaActiva)) {
        $itemsCuenta[] = ['cuenta-persona', '/cuenta/persona', _('Persona activa')];
    }
    // En el centro la ayuda ya está en el menú principal.
    $itemsCuenta[] = ['yo-ayuda', '/yo/ayuda', _('Ayuda')];
}
if (!empty($mostrarMenuBaja)) {
    $itemsCuenta[] = ['cuenta-baja', '/cuenta/baja', _('Dar de baja la cuenta')];
}
?>
<details class="user-menu">
    <summary><?= htmlspecialchars($usuarioNombre, ENT_QUOTES) ?> <span class="msg-badge" hidden></span></summary>
    <ul>
        <?php foreach ($itemsCuenta as [$id, $href, $label]): ?>
            <li>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"
                   class="<?= $navCuenta === $id ? 'on' : '' ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?><?php if ($id === 'mensajes'): ?> <span class="msg-badge" hidden></span><?php endif; ?></a>
            </li>
        <?php endforeach; ?>
        <li class="user-menu-sep" role="separator"></li>
        <li><a href="/logout"><?= _("Salir") ?></a></li>
    </ul>
</details>
