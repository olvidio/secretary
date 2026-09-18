<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\importacion\domain\services\InterpretarExencionExcel;

final class InterpretarExencionExcelTest extends TestCase
{
    public function testExencionDeUnoADocePasaANoAportaYSinExencion(): void
    {
        $r = InterpretarExencionExcel::de(1, 12, null, null, true);
        self::assertNull($r['mesExentoInicio']);
        self::assertNull($r['mesExentoFin']);
        self::assertNull($r['mesExento2Inicio']);
        self::assertNull($r['mesExento2Fin']);
        self::assertFalse($r['aportaGenerales']);
    }

    public function testExencionUnoADoceTambienBorraElSegundoIntervalo(): void
    {
        $r = InterpretarExencionExcel::de(1, 12, 3, 4, true);
        self::assertNull($r['mesExentoInicio']);
        self::assertNull($r['mesExento2Inicio']);
        self::assertNull($r['mesExento2Fin']);
        self::assertFalse($r['aportaGenerales']);
    }

    public function testExencionParcialSeConservaYRespetaAporta(): void
    {
        $r = InterpretarExencionExcel::de(9, 12, null, null, true);
        self::assertSame(9, $r['mesExentoInicio']);
        self::assertSame(12, $r['mesExentoFin']);
        self::assertTrue($r['aportaGenerales']);
    }

    public function testSinExencionRespetaAportaPorDefecto(): void
    {
        $r = InterpretarExencionExcel::de(null, null, null, null, false);
        self::assertNull($r['mesExentoInicio']);
        self::assertFalse($r['aportaGenerales']);
    }
}
