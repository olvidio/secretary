<?php

declare(strict_types=1);

namespace Tests\Soporte;

use PDO;
use src\acceso\application\AsegurarLibroPersonalIdentidad;
use src\acceso\application\IniciarSesion;
use src\acceso\application\RegistrarUsuario;
use src\acceso\application\ResolverPersonaActiva;
use src\acceso\application\VerificarSegundoFactor;
use src\acceso\domain\contracts\CifradorSecretos;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\ambito\application\CrearEjercicio;
use src\ambito\application\SincronizarConfiguracionConEjercicio;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\cierre\application\GenerarApertura;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\infrastructure\persistence\PdoPersonaRepository;

/** Cableado mínimo de acceso para tests de integración. */
final class ServiciosAcceso
{
    public static function identidades(PDO $pdo): PdoIdentidadRepository
    {
        return new PdoIdentidadRepository($pdo);
    }

    public static function libroPersonal(PDO $pdo, ?IdentidadRepository $identidades = null): AsegurarLibroPersonalIdentidad
    {
        $identidades ??= self::identidades($pdo);
        $centros = new PdoCentroRepository($pdo);
        $cuentas = new PdoCuentaRepository($pdo);
        $ejercicios = new PdoEjercicioRepository($pdo);
        $asientos = new PdoAsientoRepository($pdo);
        $config = new PdoConfiguracionRepository($pdo);
        $crearEjercicio = new CrearEjercicio(
            $ejercicios,
            new GenerarApertura($ejercicios, $asientos, $cuentas),
            new SincronizarConfiguracionConEjercicio($config, $centros),
        );

        return new AsegurarLibroPersonalIdentidad(
            $pdo,
            $centros,
            $crearEjercicio,
            $identidades,
            new PdoPersonaRepository($pdo),
            new AsegurarPlanPersonal($cuentas),
        );
    }

    public static function registrarUsuario(PDO $pdo): RegistrarUsuario
    {
        $identidades = self::identidades($pdo);

        return new RegistrarUsuario(
            $identidades,
            new PdoPersonaRepository($pdo),
            self::libroPersonal($pdo, $identidades),
        );
    }

    public static function iniciarSesion(PDO $pdo, ?IdentidadRepository $identidades = null): IniciarSesion
    {
        $identidades ??= self::identidades($pdo);

        return new IniciarSesion(
            $identidades,
            new ResolverPersonaActiva($identidades),
            self::libroPersonal($pdo, $identidades),
        );
    }

    public static function verificarSegundoFactor(
        PDO $pdo,
        CifradorSecretos $cifrador,
        string $pimiento,
        ?IdentidadRepository $identidades = null,
    ): VerificarSegundoFactor {
        $identidades ??= self::identidades($pdo);

        return new VerificarSegundoFactor(
            $identidades,
            $cifrador,
            new ResolverPersonaActiva($identidades),
            self::libroPersonal($pdo, $identidades),
            $pimiento,
        );
    }
}
