<?php

declare(strict_types=1);

namespace Tests\unit;

use frontend\shared\config\CatalogoMenus;
use PHPUnit\Framework\TestCase;

final class CatalogoMenusTest extends TestCase
{
    public function testCadaItemEstaEnExcelYBurger(): void
    {
        $navs = array_column(CatalogoMenus::items(), 'nav');
        self::assertSame($navs, array_values(array_unique($navs)));
        foreach (['excel', 'burger'] as $layout) {
            $enGrupos = [];
            foreach (CatalogoMenus::grupos($layout) as $grupo) {
                foreach ($grupo['items'] as $item) {
                    $enGrupos[] = $item['nav'];
                }
            }
            sort($navs);
            $ordenados = $enGrupos;
            sort($ordenados);
            self::assertSame($navs, $ordenados, 'Ítems sin grupo en layout ' . $layout);
        }
    }

    public function testPantallasDelCentroCoincidenConLasRutas(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/frontend/shared/config/routes.php');
        preg_match_all("/\\['(\\/[^']*)', '[^']+', '([^']+)'\\]/", $routes, $m);
        $navRutas = $m[2];
        sort($navRutas);

        $navCatalogo = array_column(array_merge(
            CatalogoMenus::items(),
            CatalogoMenus::pantallasSinMenu(),
        ), 'nav');
        sort($navCatalogo);

        self::assertSame($navRutas, $navCatalogo, 'Hay pantallas nuevas o menús huérfanos');
    }

    public function testBurgerTieneLasCategoriasPedidas(): void
    {
        $ids = array_column(CatalogoMenus::grupos('burger'), 'id');
        self::assertSame(
            ['parametros', 'presupuestos', 'movimientos', 'resumenes', 'plan-contable', 'ayuda'],
            $ids
        );
    }
}
