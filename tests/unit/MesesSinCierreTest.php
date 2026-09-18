<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\cierre\domain\services\MesesSinCierre;

final class MesesSinCierreTest extends TestCase
{
    public function testDetectaMesesConGastosSinCierre(): void
    {
        $r = (new MesesSinCierre())->ejecutar(
            ['2026-01', '2026-02'],
            ['2026-01' => false, '2026-02' => true],
            ['2026-01' => '100,00', '2026-02' => '200,00'],
        );

        self::assertFalse($r['ok']);
        self::assertCount(1, $r['meses']);
        self::assertSame('2026-02', $r['meses'][0]['ym']);
        self::assertSame('febrero', $r['meses'][0]['mes_es']);
    }

    public function testIgnoraMesesSinReparto(): void
    {
        $r = (new MesesSinCierre())->ejecutar(
            ['2026-03'],
            ['2026-03' => false],
            ['2026-03' => '0,00'],
        );

        self::assertTrue($r['ok']);
        self::assertSame([], $r['meses']);
    }

    public function testMesesAnterioresAlCierre(): void
    {
        $inicio = new DateTimeImmutable('2026-01-15');
        $cierre = new DateTimeImmutable('2026-03-20');

        self::assertSame(
            ['2026-01', '2026-02'],
            MesesSinCierre::mesesAnterioresAlCierre($inicio, $cierre),
        );
    }

    public function testSinMesesAnterioresSiCierreEsElPrimero(): void
    {
        $inicio = new DateTimeImmutable('2026-03-01');
        $cierre = new DateTimeImmutable('2026-03-31');

        self::assertSame([], MesesSinCierre::mesesAnterioresAlCierre($inicio, $cierre));
    }
}
