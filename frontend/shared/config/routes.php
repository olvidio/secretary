<?php

declare(strict_types=1);

use FastRoute\RouteCollector;
use frontend\shared\http\PageController;

return static function (RouteCollector $r): void {
    $r->addRoute('GET', '/login', [PageController::class, 'login']);
    $r->addRoute('GET', '/registro', [PageController::class, 'registro']);
    $r->addRoute('GET', '/totp-activar', [PageController::class, 'totpActivar']);
    $r->addRoute('GET', '/totp-verificar', [PageController::class, 'totpVerificar']);
    $r->addRoute('GET', '/totp-codigos', [PageController::class, 'totpCodigos']);
    $r->addRoute('GET', '/elegir-centro', [PageController::class, 'elegirCentro']);
    $r->addRoute('GET', '/elegir-persona', [PageController::class, 'elegirPersona']);
    $r->addRoute('GET', '/yo', [PageController::class, 'yo']);
    $r->addRoute('GET', '/yo/movimientos', [PageController::class, 'yoMovimientos']);
    $r->addRoute('GET', '/yo/categorias', [PageController::class, 'yoCategorias']);
    $r->addRoute('GET', '/yo/banco', [PageController::class, 'yoBanco']);
    $r->addRoute('GET', '/yo/remesas', [PageController::class, 'yoRemesas']);
    $r->addRoute('GET', '/yo/cierre', [PageController::class, 'yoCierre']);
    $r->addRoute('GET', '/yo/centros', [PageController::class, 'yoCentros']);
    $r->addRoute('GET', '/yo/ayuda', [PageController::class, 'yoAyuda']);
    $cuenta = [
        ['/cuenta/mail', 'acceso/view/cuenta_mail.php', 'cuenta-mail'],
        ['/cuenta/password', 'acceso/view/cuenta_password.php', 'cuenta-password'],
        ['/cuenta/totp', 'acceso/view/cuenta_totp.php', 'cuenta-totp'],
        ['/cuenta/layout', 'acceso/view/cuenta_layout.php', 'cuenta-layout'],
        ['/cuenta/idioma', 'acceso/view/cuenta_idioma.php', 'cuenta-idioma'],
        ['/cuenta/centro', 'acceso/view/cuenta_centro.php', 'cuenta-centro'],
        ['/cuenta/persona', 'acceso/view/cuenta_persona.php', 'cuenta-persona'],
        ['/cuenta/tipo', 'acceso/view/cuenta_tipo.php', 'cuenta-tipo'],
        ['/cuenta/copias', 'acceso/view/cuenta_copias.php', 'cuenta-copias'],
    ];
    foreach ($cuenta as [$path, $view, $nav]) {
        $r->addRoute('GET', $path, [PageController::class, 'cuenta', ['view' => $view, 'nav' => $nav]]);
    }
    $pages = [
        ['/', 'shared/view/home.php', 'inicio'],
        ['/configuracion', 'configuracion/view/form.php', 'configuracion'],
        ['/centros', 'ambito/view/centros.php', 'centros'],
        ['/copias', 'shared/view/copias.php', 'copias'],
        ['/nombres', 'personas/view/listado.php', 'nombres'],
        ['/presupuesto-p', 'presupuestos/view/form.php', 'presupuesto-p'],
        ['/presupuesto-g', 'presupuestos/view/form.php', 'presupuesto-g'],
        ['/apuntes', 'apuntes/view/listado.php', 'apuntes'],
        ['/cierre', 'cierre/view/cierre.php', 'cierre'],
        ['/entrada-p', 'apuntes/view/entrada.php', 'entrada-p'],
        ['/entrada-g', 'apuntes/view/entrada.php', 'entrada-g'],
        ['/613-p', 'informes/view/resumen613.php', '613-p'],
        ['/613-g', 'informes/view/resumen613.php', '613-g'],
        ['/e37', 'informes/view/e37.php', 'e37'],
        ['/e37-resumen', 'informes/view/e37_resumen.php', 'e37-resumen'],
        ['/fecha-cierre', 'configuracion/view/fecha_cierre.php', 'fecha-cierre'],
        ['/saldos', 'informes/view/saldos.php', 'saldos'],
        ['/comprobaciones', 'informes/view/comprobaciones.php', 'comprobaciones'],
        ['/conceptos-p', 'conceptos/view/listado.php', 'conceptos-p'],
        ['/conceptos-g', 'conceptos/view/listado.php', 'conceptos-g'],
        ['/plantillas-p', 'plantillas/view/listado.php', 'plantillas-p'],
        ['/plantillas-g', 'plantillas/view/listado.php', 'plantillas-g'],
        ['/por-concepto', 'informes/view/por_concepto.php', 'por-concepto'],
        ['/ayuda', 'ayuda/view/ayuda.php', 'ayuda'],
        ['/arqueo-p', 'arqueo/view/form.php', 'arqueo-p'],
        ['/arqueo-g', 'arqueo/view/form.php', 'arqueo-g'],
        ['/ejercicios', 'ambito/view/ejercicios.php', 'ejercicios'],
        ['/tesoreria', 'ambito/view/tesoreria.php', 'tesoreria'],
        ['/traspasos', 'asientos/view/traspasos.php', 'traspasos'],
        ['/remesas', 'remesas/view/listado.php', 'remesas'],
    ];
    foreach ($pages as [$path, $view, $nav]) {
        $extra = ['view' => $view, 'nav' => $nav];
        if ($nav === 'entrada-p') {
            $extra['cuenta'] = 'P';
        }
        if ($nav === 'entrada-g') {
            $extra['cuenta'] = 'G';
        }
        if ($nav === '613-p') {
            $extra['informe'] = 'P';
        }
        if ($nav === '613-g') {
            $extra['informe'] = 'G';
        }
        if ($nav === 'arqueo-p') {
            $extra['arqueo'] = 'P';
        }
        if ($nav === 'arqueo-g') {
            $extra['arqueo'] = 'G';
        }
        if ($nav === 'presupuesto-p') {
            $extra['presupuesto'] = 'P';
        }
        if ($nav === 'presupuesto-g') {
            $extra['presupuesto'] = 'G';
        }
        if ($nav === 'conceptos-p') {
            $extra['cuenta'] = 'P';
        }
        if ($nav === 'conceptos-g') {
            $extra['cuenta'] = 'G';
        }
        if ($nav === 'plantillas-p') {
            $extra['cuenta'] = 'P';
        }
        if ($nav === 'plantillas-g') {
            $extra['cuenta'] = 'G';
        }
        $r->addRoute('GET', $path, [PageController::class, 'page', $extra]);
    }
};
