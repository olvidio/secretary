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
        self::assertSame('I', $defs[0]['grupo']);
        self::assertSame('VII', $defs[array_search('71', $codigos, true)]['grupo']);
        self::assertContains('212', $codigos);
        self::assertLessThan(
            array_search('22', $codigos, true),
            array_search('212', $codigos, true),
        );
    }

    public function testCodigosLabores(): void
    {
        $partidas = CatalogoPlanesContables::partidasLaboresPorDefecto();
        self::assertSame(['71', '72', '73', '74', '75', '76'], Estructura613P::codigosLabores($partidas));
    }
}
