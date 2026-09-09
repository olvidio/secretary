<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES) ?>">
    <title>Secretario</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
<header class="ribbon">
    <div class="ribbon-top">
        <strong>Secretario</strong>
        <?php if (!empty($centroNombre)): ?>
            <span class="centro"><?= htmlspecialchars((string) $centroNombre, ENT_QUOTES) ?></span>
        <?php endif; ?>
        <span class="user"><?= htmlspecialchars((string) ($usuario ?? ''), ENT_QUOTES) ?></span>
        <a href="/logout">Salir</a>
    </div>
    <nav>
        <div class="group">
            <span>Inicio</span>
            <a href="/configuracion" class="<?= ($nav ?? '') === 'configuracion' ? 'on' : '' ?>">Configuración</a>
            <a href="/centros" class="<?= ($nav ?? '') === 'centros' ? 'on' : '' ?>">Centros</a>
            <a href="/nombres" class="<?= ($nav ?? '') === 'nombres' ? 'on' : '' ?>">Nombres</a>
        </div>
        <div class="group">
            <span>Presupuestos</span>
            <a href="/presupuesto-p" class="<?= ($nav ?? '') === 'presupuesto-p' ? 'on' : '' ?>">Presupuesto P</a>
            <a href="/presupuesto-g" class="<?= ($nav ?? '') === 'presupuesto-g' ? 'on' : '' ?>">Presupuesto G</a>
        </div>
        <div class="group">
            <span>Personales y generales</span>
            <a href="/apuntes" class="<?= ($nav ?? '') === 'apuntes' ? 'on' : '' ?>">Apuntes</a>
            <a href="/cierre" class="<?= ($nav ?? '') === 'cierre' ? 'on' : '' ?>">Cierre de mes</a>
        </div>
        <div class="group">
            <span>Personales</span>
            <a href="/entrada-p" class="<?= ($nav ?? '') === 'entrada-p' ? 'on' : '' ?>">Entrada P</a>
            <a href="/613-p" class="<?= ($nav ?? '') === '613-p' ? 'on' : '' ?>">613 P</a>
            <a href="/e37" class="<?= ($nav ?? '') === 'e37' ? 'on' : '' ?>">Cuentas personales</a>
            <a href="/e37-resumen" class="<?= ($nav ?? '') === 'e37-resumen' ? 'on' : '' ?>">Resumen E37</a>
            <a href="/remesas" class="<?= ($nav ?? '') === 'remesas' ? 'on' : '' ?>">Remesas</a>
        </div>
        <div class="group">
            <span>Generales</span>
            <a href="/entrada-g" class="<?= ($nav ?? '') === 'entrada-g' ? 'on' : '' ?>">Entrada G</a>
            <a href="/613-g" class="<?= ($nav ?? '') === '613-g' ? 'on' : '' ?>">613 G</a>
        </div>
        <div class="group">
            <span>Utilidades</span>
            <a href="/fecha-cierre" class="<?= ($nav ?? '') === 'fecha-cierre' ? 'on' : '' ?>">Fecha cierre</a>
            <a href="/saldos" class="<?= ($nav ?? '') === 'saldos' ? 'on' : '' ?>">Saldos</a>
            <a href="/conceptos-p" class="<?= ($nav ?? '') === 'conceptos-p' ? 'on' : '' ?>">Conceptos P</a>
            <a href="/conceptos-g" class="<?= ($nav ?? '') === 'conceptos-g' ? 'on' : '' ?>">Conceptos G</a>
            <a href="/plantillas-p" class="<?= ($nav ?? '') === 'plantillas-p' ? 'on' : '' ?>">Plantillas P</a>
            <a href="/plantillas-g" class="<?= ($nav ?? '') === 'plantillas-g' ? 'on' : '' ?>">Plantillas G</a>
            <a href="/por-concepto" class="<?= ($nav ?? '') === 'por-concepto' ? 'on' : '' ?>">Por concepto</a>
            <a href="/ejercicios" class="<?= ($nav ?? '') === 'ejercicios' ? 'on' : '' ?>">Ejercicios</a>
            <a href="/tesoreria" class="<?= ($nav ?? '') === 'tesoreria' ? 'on' : '' ?>">Tesorería</a>
            <a href="/traspasos" class="<?= ($nav ?? '') === 'traspasos' ? 'on' : '' ?>">Traspasos</a>
            <a href="/ayuda" class="<?= ($nav ?? '') === 'ayuda' ? 'on' : '' ?>">Ayuda</a>
        </div>
    </nav>
</header>
<main>
<?php
if (!empty($contentView) && is_file($contentView)) {
    include $contentView;
}
?>
</main>
<script src="/js/app.js"></script>
</body>
</html>
