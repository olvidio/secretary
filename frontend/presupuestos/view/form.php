<?php
$cuenta = $cuentaPresupuesto ?? 'P';
if ($cuenta === 'P') {
    require __DIR__ . '/form_p613.php';
    return;
}
if ($cuenta === 'G') {
    require __DIR__ . '/form_g613.php';
    return;
}
