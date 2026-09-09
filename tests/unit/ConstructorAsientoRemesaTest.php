<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\remesas\domain\services\ConstructorAsientoRemesa;

final class ConstructorAsientoRemesaTest extends TestCase
{
    public function testGastoEIngresoCuadranContraLaCuentaPersonal(): void
    {
        $a = ConstructorAsientoRemesa::construir(
            1,
            9,
            new DateTimeImmutable('2026-01-31'),
            'Remesa AA 01/2026 v1',
            44,
            90,
            [
                ['cuenta_id' => 22, 'tipo' => 'gasto', 'importe_cents' => 1250],
                ['cuenta_id' => 11, 'tipo' => 'ingreso', 'importe_cents' => 5000],
            ],
        );
        self::assertNotNull($a);
        self::assertSame('P', $a->libro);
        self::assertSame('remesa', $a->tipo);
        self::assertSame('remesa', $a->origen);
        self::assertSame(44, $a->remesaId);
        $a->assertCuadre();
        $porCuenta = [];
        foreach ($a->movimientos as $mov) {
            $porCuenta[$mov->cuentaId] = $mov;
        }
        self::assertSame(1250, $porCuenta[22]->debeCents);
        self::assertSame(5000, $porCuenta[11]->haberCents);
        self::assertSame(3750, $porCuenta[90]->debeCents);
    }

    public function testSinLineasNoHayAsiento(): void
    {
        $a = ConstructorAsientoRemesa::construir(
            1,
            9,
            new DateTimeImmutable('2026-01-31'),
            null,
            44,
            90,
            [],
        );
        self::assertNull($a);
    }
}
