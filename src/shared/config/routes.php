<?php

declare(strict_types=1);

use FastRoute\RouteCollector;
use src\administracion\infrastructure\http\AdminCentroController;
use src\administracion\infrastructure\http\AdminLegalController;
use src\administracion\infrastructure\http\AdminPlanController;
use src\administracion\infrastructure\http\AdminUsuarioController;
use src\ambito\infrastructure\http\CentroController;
use src\ambito\infrastructure\http\CopiaCentroController;
use src\ambito\infrastructure\http\EjercicioController;
use src\ambito\infrastructure\http\TesoreriaController;
use src\apuntes\infrastructure\http\ApunteController;
use src\apuntes\infrastructure\http\BancoCentroController;
use src\apuntes\infrastructure\http\EntradaPeriodicaController;
use src\apuntes\infrastructure\http\PlantillaApunteController;
use src\arqueo\infrastructure\http\ArqueoController;
use src\arqueo\infrastructure\http\ClubArqueoController;
use src\ayuda\infrastructure\http\AyudaController;
use src\asientos\infrastructure\http\TraspasoController;
use src\cierre\infrastructure\http\CierreController;
use src\conceptos\infrastructure\http\ConceptoController;
use src\configuracion\infrastructure\http\ConfiguracionController;
use src\grisbi\infrastructure\http\GrisbiController;
use src\informes\infrastructure\http\InformeController;
use src\listados\infrastructure\http\AportacionesSgController;
use src\listados\infrastructure\http\ListadoController;
use src\personas\infrastructure\http\PersonaController;
use src\personas\infrastructure\http\VinculoCentroController;
use src\plan\infrastructure\http\ClubCuentasController;
use src\plan\infrastructure\http\DestinosSgController;
use src\plan\infrastructure\http\PartidaLaboresController;
use src\presupuestos\infrastructure\http\PresupuestoController;
use src\presupuestos\infrastructure\http\PrevisionController;
use src\acceso\infrastructure\http\AuthController;
use src\acceso\infrastructure\http\PreferenciaController;
use src\personal\infrastructure\http\BancoPersonalController;
use src\personal\infrastructure\http\CopiaPersonalController;
use src\personal\infrastructure\http\PersonalController;
use src\remesas\infrastructure\http\RemesaController;
use src\disponible\infrastructure\http\DisponibleController;
use src\envio_dl\infrastructure\http\EnvioDlController;
use src\shared\infrastructure\http\CopiaSeguridadController;

return static function (RouteCollector $r): void {
    $r->addRoute('POST', '/api/login', [AuthController::class, 'login']);
    $r->addRoute('POST', '/login', [AuthController::class, 'login']);
    $r->addRoute('POST', '/olvide-contrasena', [AuthController::class, 'solicitarRestablecer']);
    $r->addRoute('POST', '/restablecer-contrasena', [AuthController::class, 'aplicarRestablecer']);
    $r->addRoute('POST', '/api/registro', [AuthController::class, 'registro']);
    $r->addRoute('POST', '/registro', [AuthController::class, 'registro']);
    $r->addRoute('POST', '/api/registro/reenviar', [AuthController::class, 'reenviarVerificacion']);
    $r->addRoute('POST', '/registro/reenviar', [AuthController::class, 'reenviarVerificacion']);
    $r->addRoute('GET', '/logout', [AuthController::class, 'logout']);
    $r->addRoute('GET', '/api/csrf', [AuthController::class, 'csrf']);
    $r->addRoute('POST', '/totp-activar', [AuthController::class, 'totpConfirmar']);
    $r->addRoute('POST', '/api/totp/activar', [AuthController::class, 'totpActivar']);
    $r->addRoute('POST', '/api/totp/confirmar', [AuthController::class, 'totpConfirmar']);
    $r->addRoute('POST', '/totp-verificar', [AuthController::class, 'totpVerificar']);
    $r->addRoute('POST', '/api/totp/verificar', [AuthController::class, 'totpVerificar']);
    $r->addRoute('POST', '/elegir-centro', [AuthController::class, 'elegirCentro']);
    $r->addRoute('POST', '/elegir-cuenta', [AuthController::class, 'elegirCuenta']);
    $r->addRoute('POST', '/api/centros/elegir', [AuthController::class, 'elegirCentro']);
    $r->addRoute('POST', '/elegir-persona', [AuthController::class, 'elegirPersona']);
    $r->addRoute('POST', '/api/personas/elegir', [AuthController::class, 'elegirPersona']);
    $r->addRoute('GET', '/api/preferencias', [PreferenciaController::class, 'get']);
    $r->addRoute('POST', '/api/preferencias/layout', [PreferenciaController::class, 'guardarLayout']);
    $r->addRoute('POST', '/api/preferencias/mail', [PreferenciaController::class, 'guardarMail']);
    $r->addRoute('POST', '/api/preferencias/password', [PreferenciaController::class, 'guardarPassword']);
    $r->addRoute('POST', '/api/preferencias/totp/preparar', [PreferenciaController::class, 'totpPreparar']);
    $r->addRoute('POST', '/api/preferencias/totp/confirmar', [PreferenciaController::class, 'totpConfirmar']);
    $r->addRoute('POST', '/api/preferencias/idioma', [PreferenciaController::class, 'guardarIdioma']);
    $r->addRoute('POST', '/api/preferencias/centro', [PreferenciaController::class, 'guardarCentro']);
    $r->addRoute('POST', '/api/preferencias/persona', [PreferenciaController::class, 'guardarPersona']);
    $r->addRoute('POST', '/api/preferencias/tipo', [PreferenciaController::class, 'guardarTipo']);
    $r->addRoute('GET', '/api/preferencias/baja', [PreferenciaController::class, 'resumenBaja']);
    $r->addRoute('POST', '/api/preferencias/baja/solicitar', [PreferenciaController::class, 'solicitarBaja']);

    $r->addRoute('GET', '/api/configuracion', [ConfiguracionController::class, 'get']);
    $r->addRoute('POST', '/api/configuracion', [ConfiguracionController::class, 'save']);

    $r->addRoute('GET', '/api/centros', [CentroController::class, 'get']);
    $r->addRoute('POST', '/api/centros/import', [CentroController::class, 'import']);
    $r->addRoute('POST', '/api/centros/import-sg', [CentroController::class, 'importSg']);

    $r->addRoute('GET', '/api/admin/planes', [AdminPlanController::class, 'list']);
    $r->addRoute('POST', '/api/admin/planes', [AdminPlanController::class, 'save']);
    $r->addRoute('POST', '/api/admin/planes/{id:\d+}/borrar', [AdminPlanController::class, 'delete']);
    $r->addRoute('GET', '/api/admin/planes/{id:\d+}/conceptos', [AdminPlanController::class, 'conceptos']);
    $r->addRoute('GET', '/api/admin/planes/{id:\d+}/conceptos/export', [AdminPlanController::class, 'exportConceptos']);
    $r->addRoute('POST', '/api/admin/planes/{id:\d+}/conceptos/import', [AdminPlanController::class, 'importConceptos']);
    $r->addRoute('POST', '/api/admin/planes/{id:\d+}/conceptos', [AdminPlanController::class, 'saveConceptos']);
    $r->addRoute('GET', '/api/admin/centros', [AdminCentroController::class, 'list']);
    $r->addRoute('POST', '/api/admin/centros', [AdminCentroController::class, 'create']);
    $r->addRoute('POST', '/api/admin/centros/{id:\d+}/borrar', [AdminCentroController::class, 'delete']);
    $r->addRoute('GET', '/api/admin/usuarios', [AdminUsuarioController::class, 'list']);
    $r->addRoute('GET', '/api/admin/usuarios/{id:\d+}/borrar', [AdminUsuarioController::class, 'previewDelete']);
    $r->addRoute('POST', '/api/admin/usuarios/{id:\d+}/borrar', [AdminUsuarioController::class, 'delete']);
    $r->addRoute('POST', '/api/admin/usuarios/{id:\d+}/reactivar', [AdminUsuarioController::class, 'reactivate']);
    $r->addRoute('POST', '/api/admin/usuarios/{id:\d+}/centros/{centroId:\d+}/desvincular', [AdminUsuarioController::class, 'desvincularCentroVinculo']);
    $r->addRoute('POST', '/api/admin/usuarios/{id:\d+}/personas/{personaId:\d+}/desvincular', [AdminUsuarioController::class, 'desvincularPersonaVinculo']);
    $r->addRoute('GET', '/api/admin/usuarios/duplicados-correo', [AdminUsuarioController::class, 'listDuplicadosCorreo']);
    $r->addRoute('GET', '/api/admin/usuarios/fusionar-legacy', [AdminUsuarioController::class, 'previewFusionLegacy']);
    $r->addRoute('POST', '/api/admin/usuarios/fusionar-legacy', [AdminUsuarioController::class, 'fusionarLegacy']);
    $r->addRoute('POST', '/api/admin/usuarios/{id:\d+}/reiniciar-totp', [AdminUsuarioController::class, 'reiniciarTotp']);
    $r->addRoute('GET', '/api/admin/legal/buscar', [AdminLegalController::class, 'buscar']);
    $r->addRoute('GET', '/api/admin/legal/expediente/{id:\d+}', [AdminLegalController::class, 'ver']);
    $r->addRoute('GET', '/api/admin/legal/expediente/{id:\d+}/export', [AdminLegalController::class, 'exportar']);
    $r->addRoute('POST', '/api/centros/vaciar', [CentroController::class, 'vaciar']);
    $r->addRoute('POST', '/api/centros/usuarios', [CentroController::class, 'addUsuario']);
    $r->addRoute('POST', '/api/centros/usuarios/rol', [CentroController::class, 'cambiarRol']);
    $r->addRoute('GET', '/api/centros/partidas-labores', [PartidaLaboresController::class, 'list']);
    $r->addRoute('POST', '/api/centros/partidas-labores', [PartidaLaboresController::class, 'save']);

    $r->addRoute('GET', '/api/personas', [PersonaController::class, 'list']);
    $r->addRoute('POST', '/api/personas/num-s', [PersonaController::class, 'saveNumS']);
    $r->addRoute('POST', '/api/personas', [PersonaController::class, 'save']);
    $r->addRoute('DELETE', '/api/personas/{id:\d+}', [PersonaController::class, 'delete']);

    $r->addRoute('GET', '/api/vinculos-centro/solicitudes', [VinculoCentroController::class, 'listarCentro']);
    $r->addRoute('GET', '/api/vinculos-centro/solicitudes/{id:\d+}/candidatos', [VinculoCentroController::class, 'candidatos']);
    $r->addRoute('POST', '/api/vinculos-centro/solicitudes/{id:\d+}/aprobar', [VinculoCentroController::class, 'aprobar']);
    $r->addRoute('POST', '/api/vinculos-centro/solicitudes/{id:\d+}/rechazar', [VinculoCentroController::class, 'rechazar']);
    $r->addRoute('GET', '/api/yo/vinculos-centro', [VinculoCentroController::class, 'listarYo']);
    $r->addRoute('GET', '/api/yo/vinculos-centro/centros', [VinculoCentroController::class, 'centrosDisponibles']);
    $r->addRoute('POST', '/api/yo/vinculos-centro', [VinculoCentroController::class, 'solicitarYo']);
    $r->addRoute('POST', '/api/yo/vinculos-centro/{id:\d+}/desvincular', [VinculoCentroController::class, 'desvincularYo']);

    $r->addRoute('GET', '/api/conceptos', [ConceptoController::class, 'list']);
    $r->addRoute('GET', '/api/club/cuentas', [ClubCuentasController::class, 'list']);
    $r->addRoute('POST', '/api/club/cuentas', [ClubCuentasController::class, 'crear']);
    $r->addRoute('POST', '/api/club/plantillas', [ClubCuentasController::class, 'guardarPlantilla']);
    $r->addRoute('POST', '/api/club/plantillas/aplicar', [ClubCuentasController::class, 'aplicar']);

    $r->addRoute('GET', '/api/apuntes', [ApunteController::class, 'list']);
    $r->addRoute('GET', '/api/apuntes/sugerencias', [ApunteController::class, 'sugerencias']);
    $r->addRoute('GET', '/api/apuntes/cuadre', [ApunteController::class, 'cuadre']);
    $r->addRoute('GET', '/api/banco-centro/bancos', [BancoCentroController::class, 'bancos']);
    $r->addRoute('POST', '/api/banco-centro/preferencia', [BancoCentroController::class, 'guardarPreferencia']);
    $r->addRoute('GET', '/api/banco-centro/pendientes', [BancoCentroController::class, 'pendientes']);
    $r->addRoute('POST', '/api/banco-centro/csv', [BancoCentroController::class, 'importar']);
    $r->addRoute('POST', '/api/banco-centro/categorizar', [BancoCentroController::class, 'categorizar']);
    $r->addRoute('POST', '/api/apuntes', [ApunteController::class, 'create']);
    $r->addRoute('PUT', '/api/apuntes/{id:\d+}', [ApunteController::class, 'update']);
    $r->addRoute('DELETE', '/api/apuntes/{id:\d+}', [ApunteController::class, 'delete']);

    $r->addRoute('GET', '/api/plantillas-apunte', [PlantillaApunteController::class, 'list']);
    $r->addRoute('POST', '/api/plantillas-apunte', [PlantillaApunteController::class, 'save']);
    $r->addRoute('DELETE', '/api/plantillas-apunte/{id:\d+}', [PlantillaApunteController::class, 'delete']);

    $r->addRoute('GET', '/api/entradas-periodicas', [EntradaPeriodicaController::class, 'list']);
    $r->addRoute('POST', '/api/entradas-periodicas', [EntradaPeriodicaController::class, 'save']);
    $r->addRoute('DELETE', '/api/entradas-periodicas/{id:\d+}', [EntradaPeriodicaController::class, 'delete']);
    $r->addRoute('GET', '/api/entradas-periodicas/pendientes', [EntradaPeriodicaController::class, 'pendientes']);
    $r->addRoute('POST', '/api/entradas-periodicas/ejecutar', [EntradaPeriodicaController::class, 'ejecutar']);

    $r->addRoute('GET', '/api/cierre', [CierreController::class, 'preview']);
    $r->addRoute('POST', '/api/cierre', [CierreController::class, 'run']);
    $r->addRoute('POST', '/api/cierre/regularizar', [CierreController::class, 'regularizar']);

    $r->addRoute('GET', '/api/informes/613/{cuenta:P|G}', [InformeController::class, 'resumen613']);
    $r->addRoute('POST', '/api/informes/613/{cuenta:P|G}/manual', [InformeController::class, 'guardarManual613']);
    $r->addRoute('GET', '/api/informes/e37', [InformeController::class, 'e37']);
    $r->addRoute('GET', '/api/informes/e37-resumen', [InformeController::class, 'e37Resumen']);
    $r->addRoute('GET', '/api/informes/saldos', [InformeController::class, 'saldos']);
    $r->addRoute('GET', '/api/informes/comprobaciones-saldos', [InformeController::class, 'comprobacionesSaldos']);
    $r->addRoute('GET', '/api/informes/comprobaciones', [InformeController::class, 'comprobaciones']);
    $r->addRoute('GET', '/api/informes/tesoreria', [InformeController::class, 'tesoreria']);

    $r->addRoute('GET', '/api/destinos-sg', [DestinosSgController::class, 'list']);
    $r->addRoute('POST', '/api/destinos-sg', [DestinosSgController::class, 'save']);

    $r->addRoute('GET', '/api/presupuestos/{cuenta:P|G}', [PresupuestoController::class, 'get']);
    $r->addRoute('POST', '/api/presupuestos/{cuenta:P|G}', [PresupuestoController::class, 'save']);
    $r->addRoute('GET', '/api/previsiones/personal/opciones', [PrevisionController::class, 'opcionesPersonal']);
    $r->addRoute('GET', '/api/previsiones/personal/{personaId:\d+}', [PrevisionController::class, 'getPersonal']);
    $r->addRoute('POST', '/api/previsiones/personal/{personaId:\d+}', [PrevisionController::class, 'savePersonal']);
    $r->addRoute('GET', '/api/previsiones', [PrevisionController::class, 'getConsolidada']);
    $r->addRoute('POST', '/api/previsiones/aplicar-presupuesto', [PrevisionController::class, 'aplicarPresupuesto']);

    $r->addRoute('GET', '/api/arqueos/capuchinos', [ArqueoController::class, 'capuchinos']);
    $r->addRoute('GET', '/api/arqueos/{cuenta:P|G}', [ArqueoController::class, 'get']);
    $r->addRoute('POST', '/api/arqueos/{cuenta:P|G}', [ArqueoController::class, 'save']);
    $r->addRoute('GET', '/api/arqueos/fisica/{id:\d+}', [ArqueoController::class, 'getFisica']);
    $r->addRoute('POST', '/api/arqueos/fisica/{id:\d+}', [ArqueoController::class, 'saveFisica']);
    $r->addRoute('GET', '/api/arqueo-club', [ClubArqueoController::class, 'estado']);
    $r->addRoute('POST', '/api/arqueo-club/cuadrar', [ClubArqueoController::class, 'cuadrar']);

    $r->addRoute('GET', '/api/tesoreria', [TesoreriaController::class, 'list']);
    $r->addRoute('POST', '/api/tesoreria', [TesoreriaController::class, 'create']);
    $r->addRoute('POST', '/api/tesoreria/{id:\d+}', [TesoreriaController::class, 'editar']);
    $r->addRoute('POST', '/api/tesoreria/{id:\d+}/desactivar', [TesoreriaController::class, 'desactivar']);

    $r->addRoute('POST', '/api/traspasos', [TraspasoController::class, 'traspaso']);
    $r->addRoute('POST', '/api/prestamos-libros', [TraspasoController::class, 'prestamo']);

    $r->addRoute('GET', '/api/ejercicios', [EjercicioController::class, 'list']);
    $r->addRoute('POST', '/api/ejercicios', [EjercicioController::class, 'create']);
    $r->addRoute('POST', '/api/ejercicios/{id:\d+}/cerrar', [EjercicioController::class, 'cerrar']);
    $r->addRoute('POST', '/api/ejercicios/{id:\d+}/reabrir', [EjercicioController::class, 'reabrir']);
    $r->addRoute('POST', '/api/ejercicios/{id:\d+}/apertura', [EjercicioController::class, 'apertura']);
    $r->addRoute('POST', '/api/ejercicios/{id:\d+}/eliminar', [EjercicioController::class, 'eliminar']);

    $r->addRoute('GET', '/api/yo/resumen', [PersonalController::class, 'resumen']);
    $r->addRoute('GET', '/api/yo/movimientos', [PersonalController::class, 'movimientos']);
    $r->addRoute('GET', '/api/yo/gastos-ordinarios', [PersonalController::class, 'gastosOrdinarios']);
    $r->addRoute('POST', '/api/yo/movimientos', [PersonalController::class, 'crear']);
    $r->addRoute('PUT', '/api/yo/movimientos/{id:\d+}', [PersonalController::class, 'actualizarMovimiento']);
    $r->addRoute('POST', '/api/yo/movimientos/{id:\d+}/desdoblar', [PersonalController::class, 'desdoblarMovimiento']);
    $r->addRoute('DELETE', '/api/yo/movimientos/{id:\d+}', [PersonalController::class, 'borrarMovimiento']);
    $r->addRoute('GET', '/api/yo/categorias', [PersonalController::class, 'listarCategorias']);
    $r->addRoute('GET', '/api/yo/conceptos-generales', [PersonalController::class, 'listarConceptosGenerales']);
    $r->addRoute('GET', '/api/yo/copias', [CopiaPersonalController::class, 'list']);
    $r->addRoute('POST', '/api/yo/copias/backup', [CopiaPersonalController::class, 'backup']);
    $r->addRoute('GET', '/api/yo/copias/descargar', [CopiaPersonalController::class, 'descargar']);
    $r->addRoute('POST', '/api/yo/copias/restore', [CopiaPersonalController::class, 'restore']);
    $r->addRoute('POST', '/api/yo/copias/borrar', [CopiaPersonalController::class, 'borrar']);
    $r->addRoute('POST', '/api/yo/categorias', [PersonalController::class, 'crearCategoria']);
    $r->addRoute('GET', '/api/yo/cierre', [PersonalController::class, 'cierre']);
    $r->addRoute('POST', '/api/yo/cierre/defecto', [PersonalController::class, 'guardarCierreDefecto']);
    $r->addRoute('POST', '/api/yo/cierre/mes', [PersonalController::class, 'guardarCierreMes']);
    $r->addRoute('POST', '/api/yo/cierre/mes/borrar', [PersonalController::class, 'borrarCierreMes']);
    $r->addRoute('GET', '/api/yo/remanente', [PersonalController::class, 'remanente']);
    $r->addRoute('POST', '/api/yo/remanente', [PersonalController::class, 'guardarRemanente']);
    $r->addRoute('GET', '/api/yo/banco/bancos', [BancoPersonalController::class, 'bancos']);
    $r->addRoute('POST', '/api/yo/banco/preferencia', [BancoPersonalController::class, 'guardarPreferencia']);
    $r->addRoute('GET', '/api/yo/banco/pendientes', [BancoPersonalController::class, 'pendientes']);
    $r->addRoute('POST', '/api/yo/banco/csv', [BancoPersonalController::class, 'importar']);
    $r->addRoute('POST', '/api/yo/banco/categorizar', [BancoPersonalController::class, 'categorizar']);

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

    $r->addRoute('GET', '/api/disponible', [DisponibleController::class, 'listar']);
    $r->addRoute('POST', '/api/disponible/ajustar', [DisponibleController::class, 'ajustar']);
    $r->addRoute('POST', '/api/disponible/proponer', [DisponibleController::class, 'proponer']);
    $r->addRoute('POST', '/api/disponible/asignaciones/{id:\d+}/confirmar', [DisponibleController::class, 'confirmar']);
    $r->addRoute('GET', '/api/desgravacion-tramos', [DisponibleController::class, 'tramos']);
    $r->addRoute('POST', '/api/desgravacion-tramos', [DisponibleController::class, 'guardarTramos']);
    $r->addRoute('GET', '/api/yo/asignaciones', [DisponibleController::class, 'yoAsignaciones']);

    $r->addRoute('POST', '/api/envio-dl/proponer', [EnvioDlController::class, 'proponer']);
    $r->addRoute('POST', '/api/envio-dl/{id:\d+}/confirmar', [EnvioDlController::class, 'confirmar']);

    $r->addRoute('GET', '/api/ayuda/temas', [AyudaController::class, 'listarTemas']);
    $r->addRoute('POST', '/api/ayuda/preguntar', [AyudaController::class, 'preguntar']);

    $r->addRoute('GET', '/api/centros/copias', [CopiaCentroController::class, 'list']);
    $r->addRoute('POST', '/api/centros/copias/backup', [CopiaCentroController::class, 'backup']);
    $r->addRoute('GET', '/api/centros/copias/descargar', [CopiaCentroController::class, 'descargar']);
    $r->addRoute('POST', '/api/centros/copias/restore', [CopiaCentroController::class, 'restore']);
    $r->addRoute('POST', '/api/centros/copias/borrar', [CopiaCentroController::class, 'borrar']);

    $r->addRoute('GET', '/api/copias', [CopiaSeguridadController::class, 'list']);
    $r->addRoute('POST', '/api/copias/backup', [CopiaSeguridadController::class, 'backup']);
    $r->addRoute('GET', '/api/copias/descargar', [CopiaSeguridadController::class, 'descargar']);
    $r->addRoute('POST', '/api/copias/restore', [CopiaSeguridadController::class, 'restore']);
    $r->addRoute('POST', '/api/copias/borrar', [CopiaSeguridadController::class, 'borrar']);

    $r->addRoute('POST', '/api/grisbi/importar', [GrisbiController::class, 'importar']);
    $r->addRoute('GET', '/api/grisbi/movimientos', [GrisbiController::class, 'movimientos']);
    $r->addRoute('POST', '/api/grisbi/movimientos/{id:\d+}', [GrisbiController::class, 'editar']);
    $r->addRoute('POST', '/api/grisbi/movimientos/{id:\d+}/borrar', [GrisbiController::class, 'borrar']);
    $r->addRoute('GET', '/api/aportaciones-sg', [AportacionesSgController::class, 'list']);
    $r->addRoute('GET', '/api/listados', [ListadoController::class, 'list']);
    $r->addRoute('POST', '/api/listados', [ListadoController::class, 'save']);
    $r->addRoute('POST', '/api/listados/{id:\d+}/borrar', [ListadoController::class, 'delete']);
    $r->addRoute('GET', '/api/listados/{id:\d+}', [ListadoController::class, 'run']);
};
