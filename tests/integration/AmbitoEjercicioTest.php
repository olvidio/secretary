<?php

declare(strict_types=1);

namespace Tests\integration;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\CrearEjercicio;
use src\ambito\application\SincronizarConfiguracionConEjercicio;
use src\ambito\domain\entity\Centro;
use src\ambito\domain\entity\Ejercicio;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\cierre\application\GenerarApertura;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use PDO;
use Tests\Soporte\BaseDeDatosAislada;

/**
 * Fase 2 (docs/dev/plan_ampliaciones.md, D11 y D3): ejercicios de período libre,
 * no solapamiento y aislamiento multicentro contra la base real (PdoEjercicioRepository,
 * CrearEjercicio). `PeriodoEjercicioTest` ya cubre el cálculo puro del value object;
 * aquí se comprueba el mismo cálculo a través de la persistencia y de las reglas de
 * alta (CrearEjercicio), que es donde vive la regla de no-solapamiento de D11.
 */
final class AmbitoEjercicioTest extends TestCase
{
    use BaseDeDatosAislada;

    public function testEjercicioLibreJunioAMayoProrrateaCorrectamente(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        (new SchemaInstaller($pdo))->install();

        $centroRepo = new PdoCentroRepository($pdo);
        $ejercicioRepo = new PdoEjercicioRepository($pdo);
        $centro = $centroRepo->guardar(new Centro(null, 'CTR-LIBRE', 'Centro de período libre', 'vivienda', 'H16n'));

        $crear = $this->crearEjercicioService($pdo, $ejercicioRepo);
        $ejercicio = $crear->ejecutar([
            'centro_id' => $centro->id,
            'etiqueta' => '2026-27',
            'fecha_inicio' => '2026-06-01',
            'fecha_fin' => '2027-05-31',
            'fecha_corte' => '2026-11-30',
        ]);

        self::assertSame(12, $ejercicio->periodo()->mesesTotales());
        self::assertSame(6, $ejercicio->periodo()->mesesTranscurridos());
        self::assertTrue($ejercicio->periodo()->contiene(new DateTimeImmutable('2027-03-01')));
        self::assertFalse($ejercicio->periodo()->contiene(new DateTimeImmutable('2027-06-01')));

        // Releído de la base: la persistencia no confunde fecha_fin con fecha_corte.
        $releido = $ejercicioRepo->porId((int) $ejercicio->id);
        self::assertNotNull($releido);
        self::assertSame('2027-05-31', $releido->fechaFin->format('Y-m-d'));
        self::assertSame('2026-11-30', $releido->fechaCorte->format('Y-m-d'));
        self::assertSame(12, $releido->periodo()->mesesTotales());
        self::assertSame(6, $releido->periodo()->mesesTranscurridos());
    }

    public function testNoSePuedenSolaparDosEjerciciosDelMismoCentro(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        (new SchemaInstaller($pdo))->install();

        $centroRepo = new PdoCentroRepository($pdo);
        $ejercicioRepo = new PdoEjercicioRepository($pdo);
        $centro = $centroRepo->guardar(new Centro(null, 'CTR-SOLAPE', 'Centro de solape', 'vivienda', 'H16n'));

        $crear = $this->crearEjercicioService($pdo, $ejercicioRepo);
        $primero = $crear->ejecutar([
            'centro_id' => $centro->id,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
        ]);
        $ejercicioRepo->guardar(new Ejercicio(
            $primero->id,
            $primero->centroId,
            $primero->etiqueta,
            $primero->fechaInicio,
            $primero->fechaFin,
            $primero->fechaFin,
            'cerrado',
            $primero->ejercicioAnteriorId,
        ));

        $this->expectException(InvalidArgumentException::class);
        $crear->ejecutar([
            'centro_id' => $centro->id,
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2027-06-30',
        ]);
    }

    public function testDosCentrosNoInterfierenEntreSi(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        (new SchemaInstaller($pdo))->install();

        $centroRepo = new PdoCentroRepository($pdo);
        $ejercicioRepo = new PdoEjercicioRepository($pdo);
        $cuentaRepo = new PdoCuentaRepository($pdo);

        // El centro sembrado por SchemaInstaller::install() a partir de la configuración
        // placeholder ("Centro"): ya tiene ejercicio y plan de cuentas propios.
        $centroA = $centroRepo->porCodigo('Centro');
        self::assertNotNull($centroA);

        $centroB = $centroRepo->guardar(new Centro(null, 'CTR-B', 'Segundo centro', 'vivienda', 'H16n'));
        $crear = $this->crearEjercicioService($pdo, $ejercicioRepo);
        $ejercicioB = $crear->ejecutar([
            'centro_id' => $centroB->id,
            'fecha_inicio' => '2030-01-01',
            'fecha_fin' => '2030-12-31',
        ]);

        // El segundo centro tiene su propio ejercicio y no aparece en el listado del primero.
        $ejerciciosA = $ejercicioRepo->listarDeCentro((int) $centroA->id);
        $ejerciciosB = $ejercicioRepo->listarDeCentro((int) $centroB->id);
        self::assertNotSame([], $ejerciciosA);
        self::assertCount(1, $ejerciciosB);
        foreach ($ejerciciosA as $e) {
            self::assertNotSame($ejercicioB->id, $e->id);
        }

        // AmbitoSeeder sólo puebla el centro que sale de `configuracion`: el segundo
        // centro, creado a mano en este test, no tiene ninguna cuenta todavía, mientras
        // que el primero sí tiene el plan maestro completo. El ámbito de uno no
        // contamina al otro.
        self::assertSame([], $cuentaRepo->listarDeCentro((int) $centroB->id));
        self::assertNotSame([], $cuentaRepo->listarDeCentro((int) $centroA->id));
    }

    private function crearEjercicioService(PDO $pdo, PdoEjercicioRepository $ejercicioRepo): CrearEjercicio
    {
        $asientoRepo = new PdoAsientoRepository($pdo);
        $cuentaRepo = new PdoCuentaRepository($pdo);
        $configRepo = new PdoConfiguracionRepository($pdo);

        return new CrearEjercicio(
            $ejercicioRepo,
            new GenerarApertura($ejercicioRepo, $asientoRepo, $cuentaRepo),
            new SincronizarConfiguracionConEjercicio($configRepo),
        );
    }
}
