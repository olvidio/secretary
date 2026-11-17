<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use src\apuntes\domain\contracts\BancoCentroImportRepository;
use src\apuntes\application\BorrarApunte;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

final class BorrarApunteTest extends TestCase
{
    private BancoCentroImportRepository $banco;

    protected function setUp(): void
    {
        $this->banco = $this->createMock(BancoCentroImportRepository::class);
    }

    private function borrar(AsientoRepository $repo): BorrarApunte
    {
        return new BorrarApunte($repo, $this->banco, $this->createMock(PDO::class));
    }

    private function asignacion(string $tipo = 'normal', ?int $remesaId = null): Asiento
    {
        // Gasto 7 (dos partidas) contra 111, como lo anotaba el confirmar antiguo.
        $movs = $tipo === 'normal'
            ? [new Movimiento(null, 1, 71, 5, 10000, 0), new Movimiento(null, 2, 72, 5, 2550, 0), new Movimiento(null, 3, 111, 5, 0, 12550)]
            : [new Movimiento(null, 1, 111, 5, 12550, 0), new Movimiento(null, 2, 9, 5, 0, 12550)];

        return new Asiento(40, 3, 'P', 12, new DateTimeImmutable('2026-05-31'), 'Asignación labores', $tipo, 'asignacion', 5, $movs, remesaId: $remesaId);
    }

    public function testAsignacionPideConfirmarConElImporte(): void
    {
        $repo = $this->createMock(AsientoRepository::class);
        $repo->method('porId')->willReturn($this->asignacion());
        $repo->expects(self::once())->method('buscarLiquidacionCc')->with(3, 5, '2026-05-31', 12550, null)->willReturn(41);
        $repo->expects(self::never())->method('anularAsignacionConLiquidacion');
        $repo->expects(self::never())->method('borrar');

        $aviso = $this->borrar($repo)->ejecutar(40);

        self::assertNotNull($aviso);
        self::assertStringContainsString('125,50', $aviso);
    }

    public function testAsignacionConfirmadaSeAnulaConSuLiquidacion(): void
    {
        $repo = $this->createMock(AsientoRepository::class);
        $repo->method('porId')->willReturn($this->asignacion());
        $repo->method('buscarLiquidacionCc')->willReturn(41);
        $repo->expects(self::once())->method('anularAsignacionConLiquidacion')->with(40, 41);
        $repo->expects(self::never())->method('borrar');

        self::assertNull($this->borrar($repo)->ejecutar(40, true));
    }

    public function testSinLiquidacionNoSeBorra(): void
    {
        $repo = $this->createMock(AsientoRepository::class);
        $repo->method('porId')->willReturn($this->asignacion());
        $repo->method('buscarLiquidacionCc')->willReturn(null);
        $repo->expects(self::never())->method('anularAsignacionConLiquidacion');

        $this->expectException(InvalidArgumentException::class);
        $this->borrar($repo)->ejecutar(40, true);
    }

    public function testLiquidacionSolaNoSeBorra(): void
    {
        $repo = $this->createMock(AsientoRepository::class);
        $repo->method('porId')->willReturn($this->asignacion('asignacion_cc'));
        $repo->expects(self::never())->method('anularAsignacionConLiquidacion');

        $this->expectException(InvalidArgumentException::class);
        $this->borrar($repo)->ejecutar(40, true);
    }

    public function testAsignacionDeRemesaSigueSinBorrarse(): void
    {
        $repo = $this->createMock(AsientoRepository::class);
        $repo->method('porId')->willReturn($this->asignacion('normal', 7));
        $repo->expects(self::never())->method('anularAsignacionConLiquidacion');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('remesa');
        $this->borrar($repo)->ejecutar(40, true);
    }

    public function testApunteNormalSeBorraSinPreguntar(): void
    {
        $normal = new Asiento(50, 3, 'P', 13, new DateTimeImmutable('2026-05-31'), 'Gasto', 'normal', 'A', 5, [
            new Movimiento(null, 1, 22, 5, 1000, 0),
            new Movimiento(null, 2, 9, 5, 0, 1000),
        ]);
        $repo = $this->createMock(AsientoRepository::class);
        $repo->method('porId')->willReturn($normal);
        $repo->expects(self::once())->method('borrar')->with(50);
        $this->banco->expects(self::once())->method('descartarDeAsiento')->with(50);

        self::assertNull($this->borrar($repo)->ejecutar(50));
    }
}
