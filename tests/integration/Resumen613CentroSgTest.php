<?php

declare(strict_types=1);

namespace Tests\integration;

use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\entity\Centro;
use src\ambito\domain\entity\Ejercicio;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\ambito\infrastructure\persistence\PdoPobladorCentro;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\informes\application\ObtenerResumen613;
use src\informes\domain\services\RealizadoPorConceptoCentroSg;
use src\informes\infrastructure\persistence\PdoInforme613MesRepository;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\plan\domain\services\CatalogoPlanesContables;
use src\plan\infrastructure\persistence\PdoDestinoSgRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoSg;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class Resumen613CentroSgTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_resumen_613_sg';

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
    }

    public function testLinea41SumaApunteGastoNormal(): void
    {
        $ctx = $this->prepararCentroSg();
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $caja = $cuentas->tesoreria($ctx['centroId'], 'G', 'CAJA');
        $g41 = $cuentas->buscar($ctx['centroId'], null, 'G', '41');
        self::assertNotNull($caja?->id);
        self::assertNotNull($g41?->id);

        $asientos->guardar(new Asiento(
            null,
            $ctx['ejercicioId'],
            'G',
            null,
            new DateTimeImmutable('2026-03-10'),
            'necesidades',
            'normal',
            'manual',
            null,
            [
                new Movimiento(null, 1, $g41->id, null, 15000, 0),
                new Movimiento(null, 2, $caja->id, null, 0, 15000),
            ],
        ));

        $linea41 = $this->linea41($ctx['centroId']);
        self::assertSame('150.00', $linea41['realizado']);
    }

    /** Apunte al 41 que quedó como traspaso caja/banco (sin movimiento en G/41). */
    public function testLinea41SumaTraspasoMalClasificado(): void
    {
        $ctx = $this->prepararCentroSg();
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $caja = $cuentas->tesoreria($ctx['centroId'], 'G', 'CAJA');
        $banco = $cuentas->tesoreria($ctx['centroId'], 'G', 'BANCO');
        self::assertNotNull($caja?->id);
        self::assertNotNull($banco?->id);

        $asientos->guardar(new Asiento(
            null,
            $ctx['ejercicioId'],
            'G',
            null,
            new DateTimeImmutable('2026-04-05'),
            'antes era puente',
            'traspaso',
            'manual',
            null,
            [
                new Movimiento(null, 1, $caja->id, null, 8000, 0),
                new Movimiento(null, 2, $banco->id, null, 0, 8000),
            ],
        ));

        $linea41 = $this->linea41($ctx['centroId']);
        self::assertSame('80.00', $linea41['realizado']);
    }

    /** Destino 42 nombrado: traspaso banco→caja sin movimiento en G/42. */
    public function testLinea42DestinoSumaTraspasoMalClasificado(): void
    {
        $ctx = $this->prepararCentroSg(conDestino42: true);
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $caja = $cuentas->tesoreria($ctx['centroId'], 'G', 'CAJA');
        $banco = $cuentas->tesoreria($ctx['centroId'], 'G', 'BANCO');
        self::assertNotNull($caja?->id);
        self::assertNotNull($banco?->id);

        $asientos->guardar(new Asiento(
            null,
            $ctx['ejercicioId'],
            'G',
            null,
            new DateTimeImmutable('2026-05-12'),
            'destino mal traspaso',
            'traspaso',
            'manual',
            null,
            [
                new Movimiento(null, 1, $banco->id, null, 12000, 0),
                new Movimiento(null, 2, $caja->id, null, 0, 12000),
            ],
        ));

        $linea42 = $this->linea($ctx['centroId'], '42');
        self::assertSame('120.00', $linea42['realizado']);
    }

    /**
     * @return array{centroId:int, ejercicioId:int}
     */
    private function prepararCentroSg(bool $conDestino42 = false): array
    {
        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $config = new PdoConfiguracionRepository($this->pdo);
        $centro = $centros->guardar(new Centro(
            null,
            'SG-613',
            'Centro sg 613',
            'sg',
            'vivienda',
            CatalogoPlanesContables::CENTRO_SG,
        ));
        self::assertNotNull($centro->id);
        $ej = $ejercicios->guardar(new Ejercicio(
            null,
            $centro->id,
            '2026',
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-12-31'),
            new DateTimeImmutable('2026-01-01'),
        ));
        self::assertNotNull($ej->id);
        (new PdoPobladorCentro($this->pdo))->ejecutar($centro->id);
        if ($conDestino42) {
            self::assertNotNull($centro->id);
            $this->pdo->prepare(
                'INSERT INTO centro_destinos_sg (centro_id, codigo, etiqueta, orden)
                 VALUES (:c, \'42\', \'Formación\', 1)
                 ON CONFLICT (centro_id, codigo) DO UPDATE SET etiqueta = excluded.etiqueta'
            )->execute([':c' => $centro->id]);
            (new PdoPobladorCentro($this->pdo))->ejecutar($centro->id);
        }
        $config->guardar(new ConfiguracionCentro(
            'SG-613',
            2026,
            'Año',
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-06-30'),
            'vivienda',
            null,
            null,
        ));

        return ['centroId' => $centro->id, 'ejercicioId' => $ej->id];
    }

    /** @return array<string, mixed> */
    private function linea41(int $centroId): array
    {
        return $this->linea($centroId, '41');
    }

    /** @return array<string, mixed> */
    private function linea(int $centroId, string $codigo): array
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, $centros, $ejercicios, $centroId);
        $realizadoSg = new RealizadoPorConceptoCentroSg(
            $asientos,
            $cuentas,
            $personas,
            new ProyectorAsientoAFilaExcel(),
        );
        $caso = new ObtenerResumen613(
            $config,
            $asientos,
            new PdoPresupuestoRepository($this->pdo),
            $personas,
            $ambito,
            $this->createMock(\src\plan\domain\contracts\PartidaLaboresRepository::class),
            new PdoInforme613MesRepository($this->pdo),
            $this->createMock(\src\arqueo\domain\contracts\ArqueoRepository::class),
            $this->createMock(\src\ambito\domain\contracts\CuentaFisicaRepository::class),
            $centros,
            new PdoDestinoSgRepository($this->pdo),
            new PdoPresupuestoSg($this->pdo),
            null,
            $realizadoSg,
        );
        $payload = $caso->ejecutar('G');
        foreach ($payload['lineas'] as $l) {
            if ($l['codigo'] === $codigo) {
                return $l;
            }
        }
        self::fail('Falta la línea ' . $codigo . ' en el 613');

        return [];
    }
}
