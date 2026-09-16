<?php

declare(strict_types=1);

use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\application\AsegurarCuentaDisponiblePersona;
use src\ambito\application\CrearCentro;
use src\ambito\application\VaciarDatosCentro;
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
use src\ambito\domain\contracts\PobladorCentro;
use src\ambito\infrastructure\http\CentroController;
use src\ambito\infrastructure\persistence\PdoPobladorCentro;
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
use src\apuntes\application\ActualizarApunte;
use src\apuntes\application\BorrarApunte;
use src\apuntes\application\BorrarPlantillaApunte;
use src\apuntes\application\BuscarSugerenciasObservacion;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\CrearApuntesDeEntrada;
use src\apuntes\application\GuardarPlantillaApunte;
use src\apuntes\application\ListarApuntes;
use src\apuntes\application\ListarPlantillasApunte;
use src\apuntes\domain\contracts\ApunteRepository;
use src\apuntes\domain\contracts\PlantillaApunteRepository;
use src\apuntes\domain\services\ContrapartidasGastoGeneral;
use src\apuntes\infrastructure\http\ApunteController;
use src\apuntes\infrastructure\http\PlantillaApunteController;
use src\apuntes\infrastructure\persistence\PdoApunteRepository;
use src\apuntes\infrastructure\persistence\PdoPlantillaApunteRepository;
use src\arqueo\application\BuscarCapuchinos;
use src\ayuda\application\ListarTemasAyuda;
use src\ayuda\application\ResponderPreguntaAyuda;
use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\contracts\RepositorioDocumentacion;
use src\ayuda\domain\services\BuscadorDocumentacion;
use src\ayuda\domain\services\ConstructorPromptAyuda;
use src\ayuda\domain\services\InterpreteRespuestaIA;
use src\ayuda\infrastructure\http\AyudaController;
use src\ayuda\infrastructure\llm\ClienteChatCompatibleOpenAI;
use src\ayuda\infrastructure\persistence\DocumentacionEnDisco;
use src\ayuda\infrastructure\persistence\PdoRegistroConsultasAyuda;
use src\arqueo\application\GuardarArqueo;
use src\arqueo\domain\contracts\ArqueoRepository;
use src\arqueo\domain\services\DetectarCapuchinos;
use src\arqueo\infrastructure\http\ArqueoController;
use src\arqueo\infrastructure\persistence\PdoArqueoRepository;
use src\ambito\application\SincronizarConfiguracionConEjercicio;
use src\cierre\application\CerrarEjercicio;
use src\cierre\application\CerrarMes;
use src\cierre\domain\services\MesesSinCierre;
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
use src\informes\application\ComprobarPersonalesGenerales;
use src\informes\application\GuardarInforme613Mes;
use src\informes\application\ObtenerE37;
use src\informes\application\ObtenerResumen613;
use src\informes\application\ObtenerSaldosTesoreria;
use src\informes\domain\contracts\Informe613MesRepository;
use src\informes\domain\services\CuadreViviendaGenerales;
use src\informes\domain\services\MesesSinMovimiento;
use src\informes\infrastructure\http\InformeController;
use src\informes\infrastructure\persistence\PdoInforme613MesRepository;
use src\personas\application\BorrarPersona;
use src\personas\application\GuardarPersona;
use src\personas\application\ListarPersonas;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\infrastructure\http\PersonaController;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\presupuestos\application\GuardarPresupuesto;
use src\presupuestos\domain\contracts\PresupuestoRepository;
use src\presupuestos\infrastructure\http\PresupuestoController;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\plan\domain\contracts\PlanContableRepository;
use src\plan\infrastructure\persistence\PdoPartidaLaboresRepository;
use src\plan\application\GuardarPartidasLabores;
use src\plan\application\ListarPartidasLabores;
use src\plan\infrastructure\http\PartidaLaboresController;
use src\plan\infrastructure\persistence\PdoPlanContableRepository;
use src\acceso\application\AsegurarIdentidadCentro;
use src\acceso\application\AutorizarPeticion;
use src\acceso\application\CambiarCentroUsuario;
use src\acceso\application\CambiarPersonaUsuario;
use src\acceso\application\CambiarPasswordUsuario;
use src\acceso\application\CambiarTipoUsuario;
use src\acceso\application\ConfirmarTotp;
use src\acceso\application\GuardarEmailUsuario;
use src\acceso\application\GuardarIdiomaUsuario;
use src\acceso\application\GuardarLayoutUsuario;
use src\acceso\application\IniciarSesion;
use src\acceso\application\ResolverPersonaActiva;
use src\acceso\application\ObtenerPreferenciasUsuario;
use src\acceso\application\PrepararTotp;
use src\acceso\application\RegistrarUsuario;
use src\acceso\application\VerificarSegundoFactor;
use src\acceso\application\VincularEmailPersona;
use src\acceso\domain\contracts\AccesoRutaRepository;
use src\acceso\domain\contracts\CifradorSecretos as CifradorSecretosContrato;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\infrastructure\crypto\CifradorSecretos as CifradorSecretosInfra;
use src\acceso\infrastructure\http\AuthController;
use src\acceso\infrastructure\http\PreferenciaController;
use src\acceso\infrastructure\persistence\PdoAccesoRutaRepository;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\personal\application\AsegurarPlanPersonal;
use src\personal\application\BorrarMovimientoPersonal;
use src\personal\application\CategorizarMovimientoBanco;
use src\personal\application\CrearSubcuentaPersonal;
use src\personal\application\ImportarCsvBanco;
use src\personal\application\ListarCategoriasPersonales;
use src\personal\application\BorrarCopiaPersonal;
use src\personal\application\CrearCopiaPersonal;
use src\personal\application\ListarCopiasPersonal;
use src\personal\application\ListarConceptosGenerales;
use src\personal\application\RestaurarCopiaPersonal;
use src\personal\application\ListarMovimientosPersonales;
use src\personal\application\ListarPendientesBanco;
use src\personal\application\RegistrarMovimientoPersonal;
use src\personal\application\ResolverPersonaActual;
use src\personal\application\ResumenMensualPersonal;
use src\personal\application\BorrarCierrePersonalMes;
use src\personal\application\GuardarCierrePersonalDefecto;
use src\personal\application\GuardarCierrePersonalMes;
use src\personal\application\ResolverPeriodoPersonal;
use src\personal\domain\contracts\BancoImportRepository;
use src\personal\domain\contracts\CopiaPersonalRepository;
use src\personal\domain\contracts\PersonalBancoRepository;
use src\personal\domain\contracts\PersonalCierreRepository;
use src\personal\infrastructure\http\BancoPersonalController;
use src\personal\infrastructure\http\CopiaPersonalController;
use src\personal\infrastructure\http\PersonalController;
use src\personal\infrastructure\persistence\PdoCopiaPersonalRepository;
use src\personal\infrastructure\persistence\PdoBancoImportRepository;
use src\personal\infrastructure\persistence\PdoPersonalBancoRepository;
use src\personal\infrastructure\persistence\PdoPersonalCierreRepository;
use src\remesas\application\AceptarRemesa;
use src\remesas\application\EnviarRemesa;
use src\remesas\application\ListarRemesasCentro;
use src\remesas\application\ListarSolicitudesPersonales;
use src\remesas\application\ObtenerDetalleRemesa;
use src\remesas\application\ObtenerRemesaCentro;
use src\remesas\application\ObtenerRemesaPersonal;
use src\remesas\application\PrevisualizarRemesa;
use src\remesas\application\RegistrarGastosGeneralesDeRemesa;
use src\remesas\application\RechazarRemesa;
use src\remesas\application\ResolverMesRemesa;
use src\remesas\application\ResolverSolicitudDetalle;
use src\remesas\application\SolicitarDetalleRemesa;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\infrastructure\http\RemesaController;
use src\remesas\infrastructure\persistence\PdoRemesaRepository;
use src\shared\application\BorrarCopiaSeguridad;
use src\shared\application\CrearCopiaSeguridad;
use src\shared\application\ListarCopiasSeguridad;
use src\shared\application\RestaurarCopiaSeguridad;
use src\shared\infrastructure\http\CopiaSeguridadController;
use src\shared\infrastructure\persistence\AlmacenCopiasSeguridad;
use src\shared\infrastructure\persistence\ConnectionFactory;
use src\shared\infrastructure\persistence\PostgresDumper;
use src\shared\infrastructure\persistence\RutasCopiasSeguridad;
use function DI\autowire;
use function DI\factory;

return [
    PDO::class => factory([ConnectionFactory::class, 'fromEnv']),
    ConfiguracionRepository::class => autowire(PdoConfiguracionRepository::class),
    PersonaRepository::class => autowire(PdoPersonaRepository::class),
    ConceptoRepository::class => autowire(PdoConceptoRepository::class),
    ApunteRepository::class => autowire(PdoApunteRepository::class),
    PlantillaApunteRepository::class => autowire(PdoPlantillaApunteRepository::class),
    PresupuestoRepository::class => autowire(PdoPresupuestoRepository::class),
    ArqueoRepository::class => autowire(PdoArqueoRepository::class),
    CentroRepository::class => autowire(PdoCentroRepository::class),
    PlanContableRepository::class => autowire(PdoPlanContableRepository::class),
    PartidaLaboresRepository::class => autowire(PdoPartidaLaboresRepository::class),
    ListarPartidasLabores::class => autowire(),
    GuardarPartidasLabores::class => autowire(),
    PartidaLaboresController::class => autowire(),
    PobladorCentro::class => autowire(PdoPobladorCentro::class),
    EjercicioRepository::class => autowire(PdoEjercicioRepository::class),
    CuentaFisicaRepository::class => autowire(PdoCuentaFisicaRepository::class),
    CuentaRepository::class => autowire(PdoCuentaRepository::class),
    AsientoRepository::class => autowire(PdoAsientoRepository::class),
    RemesaRepository::class => autowire(PdoRemesaRepository::class),
    src\disponible\domain\contracts\SaldoDisponibleRepository::class => autowire(src\disponible\infrastructure\persistence\PdoSaldoDisponibleRepository::class),
    src\disponible\domain\contracts\AsignacionLaboresRepository::class => autowire(src\disponible\infrastructure\persistence\PdoAsignacionLaboresRepository::class),
    src\disponible\domain\contracts\TramosDesgravacionRepository::class => autowire(src\disponible\infrastructure\persistence\PdoTramosDesgravacionRepository::class),
    src\disponible\infrastructure\http\DisponibleController::class => autowire(),
    src\envio_dl\domain\contracts\EnvioDlRepository::class => autowire(src\envio_dl\infrastructure\persistence\PdoEnvioDlRepository::class),
    src\envio_dl\infrastructure\http\EnvioDlController::class => autowire(),
    AsegurarCuentaDisponiblePersona::class => autowire(),
    Informe613MesRepository::class => autowire(PdoInforme613MesRepository::class),
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
    BancoImportRepository::class => autowire(PdoBancoImportRepository::class),
    PersonalCierreRepository::class => autowire(PdoPersonalCierreRepository::class),
    PersonalBancoRepository::class => autowire(PdoPersonalBancoRepository::class),
    ResolverPeriodoPersonal::class => autowire(),
    GuardarCierrePersonalDefecto::class => autowire(),
    GuardarCierrePersonalMes::class => autowire(),
    BorrarCierrePersonalMes::class => autowire(),
    AccesoRutaRepository::class => autowire(PdoAccesoRutaRepository::class),
    CifradorSecretosContrato::class => factory([CifradorSecretosInfra::class, 'desdeEntorno']),
    IniciarSesion::class => autowire(),
    ResolverPersonaActiva::class => autowire(),
    CambiarPersonaUsuario::class => autowire(),
    RegistrarUsuario::class => autowire(),
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
        ResolverPersonaActiva $resolverPersona,
    ): VerificarSegundoFactor {
        return new VerificarSegundoFactor(
            $identidades,
            $cifrador,
            $resolverPersona,
            CifradorSecretosInfra::pimiento(),
        );
    }),
    AutorizarPeticion::class => autowire(),
    GuardarLayoutUsuario::class => autowire(),
    CambiarPasswordUsuario::class => autowire(),
    GuardarEmailUsuario::class => autowire(),
    GuardarIdiomaUsuario::class => autowire(),
    CambiarCentroUsuario::class => autowire(),
    CambiarTipoUsuario::class => autowire(),
    ObtenerPreferenciasUsuario::class => autowire(),
    PreferenciaController::class => autowire(),
    ContextoActual::class => factory(static function (ResolverAmbitoActual $resolver): ContextoActual {
        return $resolver->ejecutar();
    }),
    ObtenerConfiguracion::class => autowire(),
    GuardarConfiguracion::class => autowire(),
    ListarPersonas::class => autowire(),
    GuardarPersona::class => autowire(),
    BorrarPersona::class => autowire(),
    VincularEmailPersona::class => autowire(),
    AsegurarIdentidadCentro::class => autowire(),
    AsegurarCuentaCorrientePersona::class => autowire(),
    CrearCentro::class => autowire(),
    VaciarDatosCentro::class => autowire(),
    ListarConceptos::class => autowire(),
    ListarApuntes::class => autowire(),
    BuscarSugerenciasObservacion::class => autowire(),
    CrearApunte::class => autowire(),
    ActualizarApunte::class => autowire(),
    CrearApuntesDeEntrada::class => autowire(),
    ContrapartidasGastoGeneral::class => autowire(),
    BorrarApunte::class => autowire(),
    MesesSinCierre::class => autowire(),
    CerrarMes::class => autowire(),
    CerrarEjercicio::class => autowire(),
    ReabrirEjercicio::class => autowire(),
    GenerarApertura::class => autowire(),
    SincronizarConfiguracionConEjercicio::class => autowire(),
    CrearEjercicio::class => autowire(),
    ListarEjercicios::class => autowire(),
    EjercicioController::class => autowire(),
    CentroController::class => autowire(),
    ObtenerResumen613::class => autowire(),
    GuardarInforme613Mes::class => autowire(),
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
    src\personas\domain\contracts\SolicitudVinculoCentroRepository::class => autowire(
        src\personas\infrastructure\persistence\PdoSolicitudVinculoCentroRepository::class,
    ),
    src\personas\application\SolicitarVinculoCentro::class => autowire(),
    src\personas\application\ListarVinculosPersona::class => autowire(),
    src\personas\application\ListarCentrosDisponiblesPersona::class => autowire(),
    src\personas\application\ListarSolicitudesVinculoCentro::class => autowire(),
    src\personas\application\ListarCandidatosVinculoCentro::class => autowire(),
    src\personas\application\AprobarSolicitudVinculoCentro::class => autowire(),
    src\personas\application\RechazarSolicitudVinculoCentro::class => autowire(),
    src\personas\infrastructure\http\VinculoCentroController::class => autowire(),
    ConceptoController::class => autowire(),
    ApunteController::class => autowire(),
    PlantillaApunteController::class => autowire(),
    CierreController::class => autowire(),
    InformeController::class => autowire(),
    ComprobarPersonalesGenerales::class => autowire(),
    CuadreViviendaGenerales::class => autowire(),
    MesesSinMovimiento::class => autowire(),
    PresupuestoController::class => autowire(),
    DetectarCapuchinos::class => autowire(),
    BuscarCapuchinos::class => autowire(),
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
    ListarConceptosGenerales::class => autowire(),
    RegistrarGastosGeneralesDeRemesa::class => autowire(),
    CrearSubcuentaPersonal::class => autowire(),
    ImportarCsvBanco::class => autowire(),
    ListarPendientesBanco::class => autowire(),
    CategorizarMovimientoBanco::class => autowire(),
    PersonalController::class => autowire(),
    CopiaPersonalRepository::class => autowire(PdoCopiaPersonalRepository::class),
    CrearCopiaPersonal::class => autowire(),
    ListarCopiasPersonal::class => autowire(),
    RestaurarCopiaPersonal::class => autowire(),
    BorrarCopiaPersonal::class => autowire(),
    CopiaPersonalController::class => autowire(),
    BancoPersonalController::class => autowire(),
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
    PostgresDumper::class => factory(static fn (PDO $pdo): PostgresDumper => PostgresDumper::fromEnv($pdo)),
    AlmacenCopiasSeguridad::class => factory(static fn (PDO $pdo): AlmacenCopiasSeguridad => new AlmacenCopiasSeguridad(
        RutasCopiasSeguridad::directorio(),
        PostgresDumper::fromEnv($pdo),
    )),
    CrearCopiaSeguridad::class => autowire(),
    ListarCopiasSeguridad::class => autowire(),
    RestaurarCopiaSeguridad::class => autowire(),
    BorrarCopiaSeguridad::class => autowire(),
    CopiaSeguridadController::class => autowire(),
    RepositorioDocumentacion::class => factory([DocumentacionEnDisco::class, 'porDefecto']),
    RegistroConsultasAyuda::class => autowire(PdoRegistroConsultasAyuda::class),
    // El proveedor sale del entorno: con AYUDA_IA_CLAVE usa Gemini Flash-Lite
    // (gratuito) por defecto; sin clave, la ayuda responde con apartados del manual.
    ResponderPreguntaAyuda::class => factory(static function (
        RepositorioDocumentacion $documentacion,
        RegistroConsultasAyuda $registro,
    ): ResponderPreguntaAyuda {
        return new ResponderPreguntaAyuda(
            $documentacion,
            $registro,
            new ConstructorPromptAyuda(),
            new InterpreteRespuestaIA(),
            new BuscadorDocumentacion(),
            ClienteChatCompatibleOpenAI::desdeEntorno(),
            ClienteChatCompatibleOpenAI::limiteDiario(),
        );
    }),
    ListarTemasAyuda::class => autowire(),
    AyudaController::class => autowire(),
];
