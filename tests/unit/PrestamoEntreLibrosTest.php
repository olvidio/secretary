<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;

/** Cuadre de los dos asientos de un préstamo G→P sobre la misma caja. */
final class PrestamoEntreLibrosTest extends TestCase
{
    public function testDosAsientosDelPrestamoCuadranPorSeparado(): void
    {
        $fecha = new DateTimeImmutable('2026-06-01');
        $cents = 10000;

        $origenG = new Asiento(
            null,
            1,
            'G',
            null,
            $fecha,
            'Préstamo a P',
            'traspaso',
            'manual',
            null,
            [
                new Movimiento(null, 1, 10, null, $cents, 0),
                new Movimiento(null, 2, 20, null, 0, $cents),
            ],
        );
        $destinoP = new Asiento(
            null,
            1,
            'P',
            null,
            $fecha,
            'Préstamo a P',
            'traspaso',
            'manual',
            null,
            [
                new Movimiento(null, 1, 30, null, $cents, 0),
                new Movimiento(null, 2, 40, null, 0, $cents),
            ],
        );

        $origenG->assertCuadre();
        $destinoP->assertCuadre();
        self::assertSame('G', $origenG->libro);
        self::assertSame('P', $destinoP->libro);
    }
}
