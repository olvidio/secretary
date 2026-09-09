<?php

declare(strict_types=1);

use src\ambito\application\CrearCuentaFisica;
use src\ambito\application\CrearEjercicio;
use src\ambito\application\DesactivarCuentaFisica;
use src\ambito\application\ListarCuentasFisicas;
use src\ambito\application\ListarEjercicios;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\value_objects\ContextoActual;
use src\ambito\infrastructure\http\EjercicioController;
use src\ambito\infrastructure\http\TesoreriaController;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\asientos\application\ConvertirApuntesAAsientos;
use src\asientos\application\RegistrarPrestamoEntreLibros;
use src\asientos\application\RegistrarTraspasoTesoreria;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\http\TraspasoController;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\application\BorrarApunte;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\ListarApuntes;
use src\apuntes\domain\contracts\ApunteRepository;
use src\apuntes\infrastructure\http\ApunteController;
use src\apuntes\infrastructure\persistence\PdoApunteRepository;
use src\arqueo\application\GuardarArqueo;
use src\arqueo\domain\contracts\ArqueoRepository;
use src\arqueo\infrastructure\http\ArqueoController;
use src\arqueo\infrastructure\persistence\PdoArqueoRepository;
use src\ambito\application\SincronizarConfiguracionConEjercicio;
use src\cierre\application\CerrarEjercicio;
use src\cierre\application\CerrarMes;
use src\cierre\application\GenerarApertura;
use src\cierre\application\ReabrirEjercicio;
use src\cierre\infrastructure\http\CierreController;
use src\conceptos\application\ListarConceptos;
use src\conceptos\domain\contracts\ConceptoRepository;
use src\conceptos\infrastructure\http\ConceptoController;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\application\GuardarConfiguracion;
use src\configuracion\application\ObtenerConfiguracion;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\infrastructure\http\ConfiguracionController;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\informes\application\CalcularSaldos;
use src\informes\application\ObtenerE37;
use src\informes\application\ObtenerResumen613;
use src\informes\application\ObtenerSaldosTesoreria;
use src\informes\infrastructure\http\InformeController;
use src\personas\application\GuardarPersona;
use src\personas\application\ListarPersonas;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\infrastructure\http\PersonaController;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\presupuestos\application\GuardarPresupuesto;
use src\presupuestos\domain\contracts\PresupuestoRepository;
use src\presupuestos\infrastructure\http\PresupuestoController;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\acceso\application\AutorizarPeticion;
use src\acceso\application\ConfirmarTotp;
use src\acceso\application\IniciarSesion;
use src\acceso\application\PrepararTotp;
use src\acceso\application\VerificarSegundoFactor;
use src\acceso\domain\contracts\AccesoRutaRepository;
use src\acceso\domain\contracts\CifradorSecretos as CifradorSecretosContrato;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\infrastructure\crypto\CifradorSecretos as CifradorSecretosInfra;
use src\acceso\infrastructure\http\AuthController;
use src\acceso\infrastructure\persistence\PdoAccesoRutaRepository;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\personal\application\AsegurarPlanPersonal;
use src\personal\application\BorrarMovimientoPersonal;
use src\personal\application\CrearSubcuentaPersonal;
use src\personal\application\ListarCategoriasPersonales;
use src\personal\application\ListarMovimientosPersonales;
use src\personal\application\RegistrarMovimientoPersonal;
use src\personal\application\ResolverPersonaActual;
use src\personal\application\ResumenMensualPersonal;
use src\personal\infrastructure\http\PersonalController;
use src\remesas\application\AceptarRemesa;
use src\remesas\application\EnviarRemesa;
use src\remesas\application\ListarRemesasCentro;
use src\remesas\application\ListarSolicitudesPersonales;
use src\remesas\application\ObtenerDetalleRemesa;
use src\remesas\application\ObtenerRemesaCentro;
use src\remesas\application\ObtenerRemesaPersonal;
use src\remesas\application\PrevisualizarRemesa;
use src\remesas\application\RechazarRemesa;
use src\remesas\application\ResolverMesRemesa;
use src\remesas\application\ResolverSolicitudDetalle;
use src\remesas\application\SolicitarDetalleRemesa;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\infrastructure\http\RemesaController;
use src\remesas\infrastructure\persistence\PdoRemesaRepository;
use src\shared\infrastructure\persistence\ConnectionFactory;
use function DI\autowire;
use function DI\factory;

return [
    PDO::class => factory([ConnectionFactory::class, 'fromEnv']),
    ConfiguracionRepository::class => autowire(PdoConfiguracionRepository::class),
    PersonaRepository::class => autowire(PdoPersonaRepository::class),
    ConceptoRepository::class => autowire(PdoConceptoRepository::class),
    ApunteRepository::class => autowire(PdoApunteRepository::class),
    PresupuestoRepository::class => autowire(PdoPresupuestoRepository::class),
    ArqueoRepository::class => autowire(PdoArqueoRepository::class),
    CentroRepository::class => autowire(PdoCentroRepository::class),
    EjercicioRepository::class => autowire(PdoEjercicioRepository::class),
    CuentaFisicaRepository::class => autowire(PdoCuentaFisicaRepository::class),
    CuentaRepository::class => autowire(PdoCuentaRepository::class),
    AsientoRepository::class => autowire(PdoAsientoRepository::class),
    RemesaRepository::class => autowire(PdoRemesaRepository::class),
    TraductorApuntesAAsientos::class => autowire(),
    ProyectorAsientoAFilaExcel::class => autowire(),
    ConvertirApuntesAAsientos::class => autowire(),
    src\importacion\domain\contracts\ImportFilaRepository::class => autowire(src\importacion\infrastructure\persistence\PdoImportFilaRepository::class),
    src\importacion\domain\contracts\ImportEjecucionRepository::class => autowire(src\importacion\infrastructure\persistence\PdoImportEjecucionRepository::class),
    src\importacion\application\SincronizarAsientosImportados::class => autowire(),
    ResolverAmbitoActual::class => factory(static function (
        ConfiguracionRepository $config,
        CentroRepository $centros,
        EjercicioRepository $ejercicios,
    ): ResolverAmbitoActual {
        $centroId = !empty($_SESSION['centro_id']) ? (int) $_SESSION['centro_id'] : null;

        return new ResolverAmbitoActual($config, $centros, $ejercicios, $centroId);
    }),
    ResolverPersonaActual::class => factory(static function (
        IdentidadRepository $identidades,
        PersonaRepository $personas,
        EjercicioRepository $ejercicios,
        AsegurarPlanPersonal $asegurar,
    ): ResolverPersonaActual {
        $identidadId = !empty($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : null;
        $personaId = !empty($_SESSION['persona_id']) ? (int) $_SESSION['persona_id'] : null;

        return new ResolverPersonaActual($identidades, $personas, $ejercicios, $asegurar, $identidadId, $personaId);
    }),
    IdentidadRepository::class => autowire(PdoIdentidadRepository::class),
    AccesoRutaRepository::class => autowire(PdoAccesoRutaRepository::class),
    CifradorSecretosContrato::class => factory([CifradorSecretosInfra::class, 'desdeEntorno']),
    IniciarSesion::class => autowire(),
    PrepararTotp::class => autowire(),
    ConfirmarTotp::class => factory(static function (
        IdentidadRepository $identidades,
        CifradorSecretosContrato $cifrador,
    ): ConfirmarTotp {
        return new ConfirmarTotp($identidades, $cifrador, CifradorSecretosInfra::pimiento());
    }),
    VerificarSegundoFactor::class => factory(static function (
        IdentidadRepository $identidades,
        CifradorSecretosContrato $cifrador,
    ): VerificarSegundoFactor {
        return new VerificarSegundoFactor($identidades, $cifrador, CifradorSecretosInfra::pimiento());
    }),
    AutorizarPeticion::class => autowire(),
    ContextoActual::class => factory(static function (ResolverAmbitoActual $resolver): ContextoActual {
        return $resolver->ejecutar();
    }),
    ObtenerConfiguracion::class => autowire(),
    GuardarConfiguracion::class => autowire(),
    ListarPersonas::class => autowire(),
    GuardarPersona::class => autowire(),
    ListarConceptos::class => autowire(),
    ListarApuntes::class => autowire(),
    CrearApunte::class => autowire(),
    BorrarApunte::class => autowire(),
    CerrarMes::class => autowire(),
    CerrarEjercicio::class => autowire(),
    ReabrirEjercicio::class => autowire(),
    GenerarApertura::class => autowire(),
    SincronizarConfiguracionConEjercicio::class => autowire(),
    CrearEjercicio::class => autowire(),
    ListarEjercicios::class => autowire(),
    EjercicioController::class => autowire(),
    ObtenerResumen613::class => autowire(),
    ObtenerE37::class => autowire(),
    CalcularSaldos::class => autowire(),
    ObtenerSaldosTesoreria::class => autowire(),
    GuardarPresupuesto::class => autowire(),
    GuardarArqueo::class => autowire(),
    ListarCuentasFisicas::class => autowire(),
    CrearCuentaFisica::class => autowire(),
    DesactivarCuentaFisica::class => autowire(),
    RegistrarTraspasoTesoreria::class => autowire(),
    RegistrarPrestamoEntreLibros::class => autowire(),
    ConfiguracionController::class => autowire(),
    PersonaController::class => autowire(),
    ConceptoController::class => autowire(),
    ApunteController::class => autowire(),
    CierreController::class => autowire(),
    InformeController::class => autowire(),
    PresupuestoController::class => autowire(),
    ArqueoController::class => autowire(),
    TesoreriaController::class => autowire(),
    TraspasoController::class => autowire(),
    AuthController::class => autowire(),
    AsegurarPlanPersonal::class => autowire(),
    RegistrarMovimientoPersonal::class => autowire(),
    ListarMovimientosPersonales::class => autowire(),
    BorrarMovimientoPersonal::class => autowire(),
    ResumenMensualPersonal::class => autowire(),
    ListarCategoriasPersonales::class => autowire(),
    CrearSubcuentaPersonal::class => autowire(),
    PersonalController::class => autowire(),
    ResolverMesRemesa::class => autowire(),
    PrevisualizarRemesa::class => autowire(),
    EnviarRemesa::class => autowire(),
    ObtenerRemesaPersonal::class => autowire(),
    ListarSolicitudesPersonales::class => autowire(),
    ResolverSolicitudDetalle::class => autowire(),
    ListarRemesasCentro::class => autowire(),
    ObtenerRemesaCentro::class => autowire(),
    AceptarRemesa::class => autowire(),
    RechazarRemesa::class => autowire(),
    ObtenerDetalleRemesa::class => autowire(),
    SolicitarDetalleRemesa::class => factory(static function (
        ResolverAmbitoActual $ambito,
        RemesaRepository $remesas,
    ): SolicitarDetalleRemesa {
        $identidadId = !empty($_SESSION['identidad_id']) ? (int) $_SESSION['identidad_id'] : null;

        return new SolicitarDetalleRemesa($ambito, $remesas, $identidadId);
    }),
    RemesaController::class => autowire(),
    frontend\shared\http\PageController::class => autowire(),
    src\importacion\application\ImportarExcelSecretario::class => autowire(),
];
