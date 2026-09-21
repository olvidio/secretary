<?php
if (!isset($versionApp)) {
    $versionApp = \src\shared\infrastructure\VersiónDespliegue::porDefecto()->etiqueta();
}
?>
<?php if (($versionApp ?? '') !== ''): ?>
<p class="login-version">
    <a href="/changelog"><?= htmlspecialchars((string) $versionApp, ENT_QUOTES) ?></a>
</p>
<?php endif; ?>
