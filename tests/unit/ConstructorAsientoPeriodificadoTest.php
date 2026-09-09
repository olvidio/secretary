<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\asientos\domain\services\ConstructorAsientoPeriodificado;

final class ConstructorAsientoPeriodificadoTest extends TestCase
{
    public function testGastoParteEnImputacionYTesoreria(): void
    {
        $origen = new Asiento(
            null,
            10,
            'G',
            null,
            new DateTimeImmutable('2027-01-08'),
            'Luz de diciembre',
            'normal',
            'manual',
            null,
            [
                new Movimiento(null, 1, 201, null, 10000, 0),
                new Movimiento(null, 2, 50, null, 0, 10000),
            ],
            '201',
        );

        $par = ConstructorAsientoPeriodificado::partir(
            $origen,
            50,
            80,
            new DateTimeImmutable('2026-12-31'),
            new DateTimeImmutable('2027-01-08'),
            10,
            11,
        );

        $imp = $par['imputacion'];
        $tes = $par['tesoreria'];
        $imp->assertCuadre();
        $tes->assertCuadre();

        self::assertSame('2026-12-31', $imp->fecha->format('Y-m-d'));
        self::assertSame('2027-01-08', $imp->fechaOperacion()->format('Y-m-d'));
        self::assertSame(10, $imp->ejercicioId);
        self::assertSame('normal', $imp->tipo);
        self::assertSame(201, $imp->movimientos[0]->cuentaId);
        self::assertSame(10000, $imp->movimientos[0]->debeCents);
        self::assertSame(80, $imp->movimientos[1]->cuentaId);
        self::assertSame(10000, $imp->movimientos[1]->haberCents);

        self::assertSame('2027-01-08', $tes->fecha->format('Y-m-d'));
        self::assertSame('periodificacion', $tes->tipo);
        self::assertSame(11, $tes->ejercicioId);
        self::assertSame(50, $tes->movimientos[0]->cuentaId);
        self::assertSame(10000, $tes->movimientos[0]->haberCents);
        self::assertSame(80, $tes->movimientos[1]->cuentaId);
        self::assertSame(10000, $tes->movimientos[1]->debeCents);
    }

    public function testIngresoInvierteElPuente(): void
    {
        $origen = new Asiento(
            null,
            1,
            'P',
            null,
            new DateTimeImmutable('2026-02-08'),
            null,
            'normal',
            'manual',
            3,
            [
                new Movimiento(null, 1, 111, null, 0, 5000),
                new Movimiento(null, 2, 50, null, 5000, 0),
            ],
            '111',
        );

        $par = ConstructorAsientoPeriodificado::partir(
            $origen,
            50,
            80,
            new DateTimeImmutable('2026-01-31'),
            new DateTimeImmutable('2026-02-08'),
            1,
            1,
        );

        $imp = $par['imputacion'];
        $tes = $par['tesoreria'];
        $imp->assertCuadre();
        $tes->assertCuadre();
        self::assertSame(5000, $imp->movimientos[1]->debeCents);
        self::assertSame(5000, $tes->movimientos[1]->haberCents);
    }

    public function testMismaFechaNoSeParte(): void
    {
        $origen = new Asiento(
            null,
            1,
            'G',
            null,
            new DateTimeImmutable('2026-01-31'),
            null,
            'normal',
            'manual',
            null,
            [
                new Movimiento(null, 1, 201, null, 100, 0),
                new Movimiento(null, 2, 50, null, 0, 100),
            ],
            '201',
        );

        $this->expectException(InvalidArgumentException::class);
        ConstructorAsientoPeriodificado::partir(
            $origen,
            50,
            80,
            new DateTimeImmutable('2026-01-31'),
            new DateTimeImmutable('2026-01-31'),
            1,
            1,
        );
    }
}
