<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\disponible\domain\services\ConstructorAsientoAsignacion;
use src\disponible\domain\services\ConstructorAsientoLiquidacionCc;

final class ConstructorAsientoAsignacionTest extends TestCase
{
    public function testLaboresCuadranContra111(): void
    {
        $a = ConstructorAsientoAsignacion::construir(
            1,
            9,
            new DateTimeImmutable('2026-09-16'),
            11,
            [
                ['cuenta_id' => 71, 'importe_cents' => 20000],
                ['cuenta_id' => 74, 'importe_cents' => 20741],
            ],
            'Asignación labores JMG 2026-09-16',
        );
        self::assertNotNull($a);
        $a->assertCuadre();
        $por = [];
        foreach ($a->movimientos as $m) {
            $por[$m->cuentaId] = $m;
        }
        self::assertSame(20000, $por[71]->debeCents);
        self::assertSame(20741, $por[74]->debeCents);
        self::assertSame(40741, $por[11]->haberCents);
    }

    public function testLiquidacionCcBajaElSaldo(): void
    {
        $a = ConstructorAsientoLiquidacionCc::construir(
            1,
            9,
            new DateTimeImmutable('2026-09-16'),
            11,
            90,
            40741,
            'Liquidación saldo CC JMG 2026-09-16',
        );
        self::assertNotNull($a);
        self::assertSame('asignacion_cc', $a->tipo);
        $a->assertCuadre();
        $por = [];
        foreach ($a->movimientos as $m) {
            $por[$m->cuentaId] = $m;
        }
        self::assertSame(40741, $por[11]->debeCents);
        self::assertSame(40741, $por[90]->haberCents);
    }
}
