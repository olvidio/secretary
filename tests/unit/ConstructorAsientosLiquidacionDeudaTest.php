<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\cierre\domain\services\ConstructorAsientosLiquidacionDeuda;

final class ConstructorAsientosLiquidacionDeudaTest extends TestCase
{
    public function testSaldarNoUsa111YCuadraLosDosLibros(): void
    {
        [$p, $g] = ConstructorAsientosLiquidacionDeuda::construir(
            10,
            7,
            new DateTimeImmutable('2026-05-15'),
            50,
            51,
            52,
            53,
            171310,
            'Liquidación deuda de vivienda jrm',
        );

        self::assertSame('liquidacion_deuda', $p->tipo);
        self::assertSame('P', $p->libro);
        self::assertSame(171310, $p->movimientos[0]->debeCents);
        self::assertSame(50, $p->movimientos[0]->cuentaId);
        self::assertSame(171310, $p->movimientos[1]->haberCents);
        self::assertSame(51, $p->movimientos[1]->cuentaId);
        self::assertSame('G', $g->libro);
        self::assertSame(171310, $g->movimientos[0]->debeCents);
        self::assertSame(53, $g->movimientos[0]->cuentaId);
        self::assertSame(171310, $g->movimientos[1]->haberCents);
        self::assertSame(52, $g->movimientos[1]->cuentaId);
        self::assertNull($p->conceptoCodigo);
        self::assertNull($g->conceptoCodigo);

        [$sp, $sg] = ConstructorAsientosLiquidacionDeuda::construir(
            10,
            7,
            new DateTimeImmutable('2026-09-30'),
            50,
            60,
            52,
            61,
            82936,
            'Superávit de vivienda ac',
            'superavit_vivienda',
        );
        self::assertSame('superavit_vivienda', $sp->tipo);
        self::assertSame('superavit_vivienda', $sg->tipo);
        self::assertSame(60, $sp->movimientos[1]->cuentaId);
        self::assertSame(61, $sg->movimientos[0]->cuentaId);
    }
}
