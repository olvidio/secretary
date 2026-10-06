<?php

declare(strict_types=1);

namespace Tests\integration;

use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\domain\entity\Centro;
use src\ambito\domain\entity\Ejercicio;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\ambito\infrastructure\persistence\PdoPobladorCentro;
use src\asientos\application\RepararAsientosTraspasoCentroSg;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class RepararTraspasoCentroSgTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_reparar_traspaso_sg';

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

    public function testReparaTraspasoLegacyAGastoYCaja(): void
    {
        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);

        $centro = $centros->guardar(new Centro(
            null,
            'SG-REP',
            'Reparar traspaso',
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

        $caja = $cuentas->tesoreria($centro->id, 'G', 'CAJA');
        $banco = $cuentas->tesoreria($centro->id, 'G', 'BANCO');
        $g41 = $cuentas->buscar($centro->id, null, 'G', '41');
        self::assertNotNull($caja?->id);
        self::assertNotNull($banco?->id);
        self::assertNotNull($g41?->id);

        $guardado = $asientos->guardar(new Asiento(
            null,
            $ej->id,
            'G',
            null,
            new DateTimeImmutable('2026-04-01'),
            'legacy',
            'traspaso',
            'manual',
            null,
            [
                new Movimiento(null, 1, $caja->id, null, 9900, 0),
                new Movimiento(null, 2, $banco->id, null, 0, 9900),
            ],
        ));
        self::assertNotNull($guardado->id);

        $caso = new RepararAsientosTraspasoCentroSg($this->pdo, $asientos, $cuentas);
        $r = $caso->ejecutar(false, 'SG-REP');
        self::assertSame(1, $r['reparados']);

        $releido = $asientos->porId($guardado->id);
        self::assertNotNull($releido);
        self::assertSame('normal', $releido->tipo);
        self::assertCount(2, $releido->movimientos);
        self::assertSame($g41->id, $releido->movimientos[0]->cuentaId);
        self::assertSame(9900, $releido->movimientos[0]->debeCents);
        self::assertSame($caja->id, $releido->movimientos[1]->cuentaId);
        self::assertSame(9900, $releido->movimientos[1]->haberCents);
    }
}
