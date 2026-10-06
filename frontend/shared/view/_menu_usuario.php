<?php
$usuarioNombre = (string) ($usuario ?? '');
$navCuenta = (string) ($nav ?? '');
$esLibroPersonal = (($_SESSION['nivel'] ?? '') === 'persona');
$itemsCuenta = [
    ['cuenta-mail', '/cuenta/mail', _('Mail')],
    ['cuenta-password', '/cuenta/password', _('Contraseña')],
];
if ($esLibroPersonal) {
    $itemsCuenta[] = ['yo-remanente', '/yo/remanente', _('Remanente')];
}
$itemsCuenta[] = ['cuenta-totp', '/cuenta/totp', _('2FA')];
$itemsCuenta[] = ['cuenta-layout', '/cuenta/layout', _('Layout')];
$itemsCuenta[] = ['cuenta-idioma', '/cuenta/idioma', _('Idioma')];
if ($esLibroPersonal) {
    if (!empty($mostrarMenuPersonaActiva)) {
        $itemsCuenta[] = ['cuenta-persona', '/cuenta/persona', _('Persona activa')];
    }
    $itemsCuenta[] = ['cuenta-copias', '/cuenta/copias', _('Copia personal')];
    // En el centro la ayuda ya está en el menú principal.
    $itemsCuenta[] = ['yo-ayuda', '/yo/ayuda', _('Ayuda')];
}
if (!empty($mostrarMenuAmbito)) {
    $itemsCuenta[] = ['cuenta-ambito', '/cuenta/ambito', _('Ámbito')];
} elseif (!$esLibroPersonal) {
    $itemsCuenta[] = ['cuenta-centro', '/cuenta/centro', _('Centro')];
}
if (!empty($mostrarMenuBaja)) {
    $itemsCuenta[] = ['cuenta-baja', '/cuenta/baja', _('Dar de baja la cuenta')];
}
?>
<details class="user-menu">
    <summary><?= htmlspecialchars($usuarioNombre, ENT_QUOTES) ?></summary>
    <ul>
        <?php foreach ($itemsCuenta as [$id, $href, $label]): ?>
            <li>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"
                   class="<?= $navCuenta === $id ? 'on' : '' ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></a>
            </li>
        <?php endforeach; ?>
        <li><a href="/logout"><?= _("Salir") ?></a></li>
    </ul>
</details>
