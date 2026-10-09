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
        $soloClub = CatalogoMenus::navsSoloClubEnMenu();
        $navsCasa = array_values(array_diff($navs, $soloClub));
        foreach (['excel', 'burger'] as $layout) {
            $enGrupos = [];
            foreach (CatalogoMenus::grupos($layout) as $grupo) {
                foreach ($grupo['items'] as $item) {
                    $enGrupos[] = $item['nav'];
                }
            }
            sort($navsCasa);
            $ordenados = $enGrupos;
            sort($ordenados);
            self::assertSame($navsCasa, $ordenados, 'Ítems sin grupo en layout ' . $layout);
        }
        foreach ($soloClub as $nav) {
            self::assertNotContains($nav, array_merge(
                ...array_map(
                    static fn (array $g): array => array_column($g['items'], 'nav'),
                    CatalogoMenus::grupos('burger'),
                ),
            ));
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

    public function testCasaNoMuestraListados(): void
    {
        foreach (['excel', 'burger'] as $layout) {
            $navs = [];
            foreach (CatalogoMenus::gruposPara($layout, false) as $grupo) {
                foreach ($grupo['items'] as $item) {
                    $navs[] = $item['nav'];
                }
            }
            self::assertNotContains('listados', $navs, $layout);
        }
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
        self::assertSame(
            ['centro', 'talonario', 'presupuesto-informes', 'cierre-arqueo', 'plan-periodo', 'ayuda'],
            $ids,
        );
        $talonario = CatalogoMenus::gruposCentroSg()[1];
        self::assertSame('talonario', $talonario['id']);
        self::assertContains('plantillas-g', array_column($talonario['items'], 'nav'));
        $cierre = CatalogoMenus::gruposCentroSg()[3];
        self::assertSame('cierre-arqueo', $cierre['id']);
        self::assertNotContains('ayuda', array_column($cierre['items'], 'nav'));
        self::assertNotContains('conceptos-g', array_column($cierre['items'], 'nav'));
        foreach (['613-p', 'entrada-p', 'remesas', 'e37', 'centros', 'cierre', 'tesoreria', 'saldos'] as $fuera) {
            self::assertNotContains($fuera, $navs);
        }
        foreach (['nombres', 'entrada-g', 'entradas-periodicas', 'ejecutar-entradas-periodicas', 'presupuesto-g', '613-g', 'aportaciones', 'donativos-fundacion', 'plantillas-g', 'arqueo-p', 'conceptos-g', 'ejercicios'] as $dentro) {
            self::assertContains($dentro, $navs);
        }
        $presupuesto = CatalogoMenus::gruposCentroSg()[2];
        self::assertSame('presupuesto-informes', $presupuesto['id']);
        self::assertNotContains('plantillas-g', array_column($presupuesto['items'], 'nav'));
    }
}
