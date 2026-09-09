<?php

declare(strict_types=1);

use FastRoute\RouteCollector;
use src\ambito\infrastructure\http\CentroController;
use src\ambito\infrastructure\http\EjercicioController;
use src\ambito\infrastructure\http\TesoreriaController;
use src\apuntes\infrastructure\http\ApunteController;
use src\apuntes\infrastructure\http\PlantillaApunteController;
use src\arqueo\infrastructure\http\ArqueoController;
use src\asientos\infrastructure\http\TraspasoController;
use src\cierre\infrastructure\http\CierreController;
use src\conceptos\infrastructure\http\ConceptoController;
use src\configuracion\infrastructure\http\ConfiguracionController;
use src\informes\infrastructure\http\InformeController;
use src\personas\infrastructure\http\PersonaController;
use src\presupuestos\infrastructure\http\PresupuestoController;
use src\acceso\infrastructure\http\AuthController;
use src\personal\infrastructure\http\PersonalController;
use src\remesas\infrastructure\http\RemesaController;

return static function (RouteCollector $r): void {
    $r->addRoute('POST', '/api/login', [AuthController::class, 'login']);
    $r->addRoute('POST', '/login', [AuthController::class, 'login']);
    $r->addRoute('GET', '/logout', [AuthController::class, 'logout']);
    $r->addRoute('GET', '/api/csrf', [AuthController::class, 'csrf']);
    $r->addRoute('POST', '/totp-activar', [AuthController::class, 'totpConfirmar']);
    $r->addRoute('POST', '/api/totp/activar', [AuthController::class, 'totpActivar']);
    $r->addRoute('POST', '/api/totp/confirmar', [AuthController::class, 'totpConfirmar']);
    $r->addRoute('POST', '/totp-verificar', [AuthController::class, 'totpVerificar']);
    $r->addRoute('POST', '/api/totp/verificar', [AuthController::class, 'totpVerificar']);
    $r->addRoute('POST', '/elegir-centro', [AuthController::class, 'elegirCentro']);
    $r->addRoute('POST', '/api/centros/elegir', [AuthController::class, 'elegirCentro']);

    $r->addRoute('GET', '/api/configuracion', [ConfiguracionController::class, 'get']);
    $r->addRoute('POST', '/api/configuracion', [ConfiguracionController::class, 'save']);

    $r->addRoute('GET', '/api/centros', [CentroController::class, 'get']);
    $r->addRoute('POST', '/api/centros', [CentroController::class, 'create']);
    $r->addRoute('POST', '/api/centros/import', [CentroController::class, 'import']);
    $r->addRoute('POST', '/api/centros/vaciar', [CentroController::class, 'vaciar']);
    $r->addRoute('POST', '/api/centros/usuarios', [CentroController::class, 'addUsuario']);

    $r->addRoute('GET', '/api/personas', [PersonaController::class, 'list']);
    $r->addRoute('POST', '/api/personas', [PersonaController::class, 'save']);
    $r->addRoute('DELETE', '/api/personas/{id:\d+}', [PersonaController::class, 'delete']);

    $r->addRoute('GET', '/api/conceptos', [ConceptoController::class, 'list']);

    $r->addRoute('GET', '/api/apuntes', [ApunteController::class, 'list']);
    $r->addRoute('GET', '/api/apuntes/sugerencias', [ApunteController::class, 'sugerencias']);
    $r->addRoute('GET', '/api/apuntes/cuadre', [ApunteController::class, 'cuadre']);
    $r->addRoute('POST', '/api/apuntes', [ApunteController::class, 'create']);
    $r->addRoute('DELETE', '/api/apuntes/{id:\d+}', [ApunteController::class, 'delete']);

    $r->addRoute('GET', '/api/plantillas-apunte', [PlantillaApunteController::class, 'list']);
    $r->addRoute('POST', '/api/plantillas-apunte', [PlantillaApunteController::class, 'save']);
    $r->addRoute('DELETE', '/api/plantillas-apunte/{id:\d+}', [PlantillaApunteController::class, 'delete']);

    $r->addRoute('GET', '/api/cierre', [CierreController::class, 'preview']);
    $r->addRoute('POST', '/api/cierre', [CierreController::class, 'run']);

    $r->addRoute('GET', '/api/informes/613/{cuenta:P|G}', [InformeController::class, 'resumen613']);
    $r->addRoute('GET', '/api/informes/e37', [InformeController::class, 'e37']);
    $r->addRoute('GET', '/api/informes/e37-resumen', [InformeController::class, 'e37Resumen']);
    $r->addRoute('GET', '/api/informes/saldos', [InformeController::class, 'saldos']);
    $r->addRoute('GET', '/api/informes/tesoreria', [InformeController::class, 'tesoreria']);

    $r->addRoute('GET', '/api/presupuestos/{cuenta:P|G}', [PresupuestoController::class, 'get']);
    $r->addRoute('POST', '/api/presupuestos/{cuenta:P|G}', [PresupuestoController::class, 'save']);

    $r->addRoute('GET', '/api/arqueos/{cuenta:P|G}', [ArqueoController::class, 'get']);
    $r->addRoute('POST', '/api/arqueos/{cuenta:P|G}', [ArqueoController::class, 'save']);
    $r->addRoute('GET', '/api/arqueos/fisica/{id:\d+}', [ArqueoController::class, 'getFisica']);
    $r->addRoute('POST', '/api/arqueos/fisica/{id:\d+}', [ArqueoController::class, 'saveFisica']);

    $r->addRoute('GET', '/api/tesoreria', [TesoreriaController::class, 'list']);
    $r->addRoute('POST', '/api/tesoreria', [TesoreriaController::class, 'create']);
    $r->addRoute('POST', '/api/tesoreria/{id:\d+}/desactivar', [TesoreriaController::class, 'desactivar']);

    $r->addRoute('POST', '/api/traspasos', [TraspasoController::class, 'traspaso']);
    $r->addRoute('POST', '/api/prestamos-libros', [TraspasoController::class, 'prestamo']);

    $r->addRoute('GET', '/api/ejercicios', [EjercicioController::class, 'list']);
    $r->addRoute('POST', '/api/ejercicios', [EjercicioController::class, 'create']);
    $r->addRoute('POST', '/api/ejercicios/{id:\d+}/cerrar', [EjercicioController::class, 'cerrar']);
    $r->addRoute('POST', '/api/ejercicios/{id:\d+}/reabrir', [EjercicioController::class, 'reabrir']);
    $r->addRoute('POST', '/api/ejercicios/{id:\d+}/apertura', [EjercicioController::class, 'apertura']);

    $r->addRoute('GET', '/api/yo/resumen', [PersonalController::class, 'resumen']);
    $r->addRoute('GET', '/api/yo/movimientos', [PersonalController::class, 'movimientos']);
    $r->addRoute('POST', '/api/yo/movimientos', [PersonalController::class, 'crear']);
    $r->addRoute('DELETE', '/api/yo/movimientos/{id:\d+}', [PersonalController::class, 'borrarMovimiento']);
    $r->addRoute('GET', '/api/yo/categorias', [PersonalController::class, 'listarCategorias']);
    $r->addRoute('POST', '/api/yo/categorias', [PersonalController::class, 'crearCategoria']);

    $r->addRoute('GET', '/api/yo/remesas/solicitudes', [RemesaController::class, 'solicitudesPersona']);
    $r->addRoute('POST', '/api/yo/remesas/solicitudes/{id:\d+}', [RemesaController::class, 'resolverSolicitud']);
    $r->addRoute('GET', '/api/yo/remesas', [RemesaController::class, 'previsualizar']);
    $r->addRoute('POST', '/api/yo/remesas', [RemesaController::class, 'enviar']);
    $r->addRoute('GET', '/api/yo/remesas/{id:\d+}', [RemesaController::class, 'verPersonal']);

    $r->addRoute('GET', '/api/remesas', [RemesaController::class, 'listarCentro']);
    $r->addRoute('GET', '/api/remesas/{id:\d+}', [RemesaController::class, 'verCentro']);
    $r->addRoute('POST', '/api/remesas/{id:\d+}/aceptar', [RemesaController::class, 'aceptar']);
    $r->addRoute('POST', '/api/remesas/{id:\d+}/rechazar', [RemesaController::class, 'rechazar']);
    $r->addRoute('POST', '/api/remesas/{id:\d+}/lineas/{lineaId:\d+}/solicitar', [RemesaController::class, 'solicitarDetalle']);
    $r->addRoute('GET', '/api/remesas/{id:\d+}/lineas/{lineaId:\d+}/detalle', [RemesaController::class, 'detalleLinea']);
};
