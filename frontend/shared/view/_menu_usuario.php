<?php
$usuarioNombre = (string) ($usuario ?? '');
$navCuenta = (string) ($nav ?? '');
$esLibroPersonal = (($_SESSION['nivel'] ?? '') === 'persona');
$itemsCuenta = [
    ['cuenta-mail', '/cuenta/mail', 'Mail'],
    ['cuenta-password', '/cuenta/password', 'Contraseña'],
    ['cuenta-totp', '/cuenta/totp', '2FA'],
    ['cuenta-layout', '/cuenta/layout', 'Layout'],
    ['cuenta-idioma', '/cuenta/idioma', 'Idioma'],
];
if ($esLibroPersonal) {
    $itemsCuenta[] = ['cuenta-persona', '/cuenta/persona', 'Persona activa'];
    $itemsCuenta[] = ['cuenta-copias', '/cuenta/copias', 'Copia personal'];
    // En el centro la ayuda ya está en el menú principal.
    $itemsCuenta[] = ['yo-ayuda', '/yo/ayuda', 'Ayuda'];
} else {
    $itemsCuenta[] = ['cuenta-centro', '/cuenta/centro', 'Centro'];
}
$itemsCuenta[] = ['cuenta-tipo', '/cuenta/tipo', 'Tipo'];
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
        <li><a href="/logout">Salir</a></li>
    </ul>
</details>
