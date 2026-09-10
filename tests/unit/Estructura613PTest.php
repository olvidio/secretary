<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\informes\domain\services\Calculadora613;
use src\plan\domain\services\CatalogoPlanesContables;
use src\plan\domain\services\Estructura613P;

final class Estructura613PTest extends TestCase
{
    public function testH16nPorDefectoTieneSeisPartidasLabores(): void
    {
        $partidas = CatalogoPlanesContables::partidasLaboresPorDefecto();
        self::assertCount(6, $partidas);
        self::assertSame('71', $partidas[0]['codigo']);
        self::assertSame('76', $partidas[5]['codigo']);
    }

    public function testEstructuraPConSeisLaboresNoIncluye77(): void
    {
        $defs = Calculadora613::estructuraP(CatalogoPlanesContables::partidasLaboresPorDefecto());
        $codigos = array_column($defs, 'codigo');
        self::assertContains('71', $codigos);
        self::assertContains('76', $codigos);
        self::assertNotContains('77', $codigos);
        self::assertNotContains('79', $codigos);
    }

    public function testCodigosLabores(): void
    {
        $partidas = CatalogoPlanesContables::partidasLaboresPorDefecto();
        self::assertSame(['71', '72', '73', '74', '75', '76'], Estructura613P::codigosLabores($partidas));
    }
}
