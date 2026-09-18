<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\personas\domain\services\ResolverBaseLiquidable;

final class ResolverBaseLiquidableTest extends TestCase
{
    public function testPrefiereLoEscritoAMano(): void
    {
        $r = ResolverBaseLiquidable::de(1800000, 1200000, 900000);
        self::assertNotNull($r);
        self::assertSame(1800000, $r['cents']);
        self::assertSame(ResolverBaseLiquidable::ORIGEN_MANUAL, $r['origen']);
    }

    public function testSiNoHayManualUsaEl111DeLaPrevision(): void
    {
        $r = ResolverBaseLiquidable::de(null, 1200000, 900000);
        self::assertNotNull($r);
        self::assertSame(1200000, $r['cents']);
        self::assertSame(ResolverBaseLiquidable::ORIGEN_PREVISION, $r['origen']);
    }

    public function testSiNoHayPrevisionUsaEl111Proyectado(): void
    {
        $r = ResolverBaseLiquidable::de(null, null, 900000);
        self::assertNotNull($r);
        self::assertSame(900000, $r['cents']);
        self::assertSame(ResolverBaseLiquidable::ORIGEN_PROYECTADO, $r['origen']);
    }

    public function testPrevisionACeroCaeAlProyectado(): void
    {
        $r = ResolverBaseLiquidable::de(null, 0, 50000);
        self::assertNotNull($r);
        self::assertSame(50000, $r['cents']);
        self::assertSame(ResolverBaseLiquidable::ORIGEN_PROYECTADO, $r['origen']);
    }

    public function testSinDatosNoHayEstimacion(): void
    {
        self::assertNull(ResolverBaseLiquidable::de(null, null, 0));
        self::assertNull(ResolverBaseLiquidable::de(0, 0, 0));
    }
}
