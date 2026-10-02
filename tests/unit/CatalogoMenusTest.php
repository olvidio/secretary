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

    public function testClubOcultaLoDeLaCasa(): void
    {
        $navs = [];
        foreach (CatalogoMenus::gruposPara('excel', true) as $grupo) {
            self::assertNotSame([], $grupo['items']);
            foreach ($grupo['items'] as $item) {
                $navs[] = $item['nav'];
            }
        }
        foreach (['nombres', '613-p', '613-g', 'e37', 'e37-resumen', 'entrada-p', 'remesas', 'disponible', 'enviar-dl', 'conceptos-p', 'presupuesto-p', 'prevision-personal', 'cierre'] as $fuera) {
            self::assertNotContains($fuera, $navs);
        }
        self::assertNotContains('grisbi', $navs);
        self::assertNotContains('centros', $navs);
        self::assertContains('configuracion', $navs);
        self::assertContains('listados', $navs);
        self::assertContains('entrada-g', $navs);
    }

    public function testCentroSgMuestraElLibroDelExcel(): void
    {
        $ids = [];
        $navs = [];
        foreach (CatalogoMenus::gruposCentroSg() as $grupo) {
            $ids[] = $grupo['id'];
            foreach ($grupo['items'] as $item) {
                $navs[] = $item['nav'];
            }
        }
        self::assertSame(['centro', 'apuntes', 'cuentas', 'caja', 'ayuda'], $ids);
        $caja = CatalogoMenus::gruposCentroSg()[3];
        self::assertSame('caja', $caja['id']);
        self::assertNotContains('ayuda', array_column($caja['items'], 'nav'));
        foreach (['613-p', 'entrada-p', 'remesas', 'e37', 'centros', 'cierre', 'tesoreria', 'saldos'] as $fuera) {
            self::assertNotContains($fuera, $navs);
        }
        foreach (['nombres', 'entrada-g', 'presupuesto-g', '613-g', 'aportaciones', 'arqueo-g'] as $dentro) {
            self::assertContains($dentro, $navs);
        }
    }
}
