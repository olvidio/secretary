<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\presupuestos\domain\services\AgrupadorPrevision613P;

final class AgrupadorPrevision613PTest extends TestCase
{
    public function testFiltrar212SinUso(): void
    {
        $filtradas = AgrupadorPrevision613P::filtrar212SinUso([
            self::linea('211', 'II', 1000, [1000]),
            self::linea('212', 'II', 0, [0]),
            self::linea('22', 'II', 500, [500]),
        ]);
        self::assertSame(['211', '22'], array_column($filtradas, 'codigo'));

        $con212 = AgrupadorPrevision613P::filtrar212SinUso([
            self::linea('212', 'II', 0, [12000]),
        ]);
        self::assertCount(1, $con212);
        self::assertSame('212', $con212[0]['codigo']);
    }

    public function testInsertaPadresDisponibleYSaldoFinal(): void
    {
        $filas = AgrupadorPrevision613P::filas([
            self::linea('111', 'I', 10000, [4000, 6000]),
            self::linea('12', 'I', 2000, [2000, 0]),
            self::linea('211', 'II', 3000, [1000, 2000]),
            self::linea('22', 'II', 1000, [0, 1000]),
            self::linea('4', 'IV', 500, [500, 0]),
            self::linea('51', 'V', 100, [100, 0]),
            self::linea('52', 'V', 50, [0, 50]),
            self::linea('6', 'VI', 200, [0, 200]),
            self::linea('71', 'VII', 800, [300, 500]),
            self::linea('9', 'IX', -100, [-40, -60]),
        ]);
        $porGrupo = [];
        foreach ($filas as $f) {
            $porGrupo[$f['grupo']][] = $f;
        }
        self::assertSame('padre', $porGrupo['I'][0]['tipo']);
        self::assertSame(12000, $porGrupo['I'][0]['total_cents']);
        self::assertCount(3, $porGrupo['I']);
        self::assertSame('total', $porGrupo['III'][0]['tipo']);
        self::assertSame(8000, $porGrupo['III'][0]['total_cents']);
        self::assertSame([5000, 3000], $porGrupo['III'][0]['personas_cents']);
        self::assertSame('padre', $porGrupo['IV'][0]['tipo']);
        self::assertSame('4', $porGrupo['IV'][0]['codigo']);
        self::assertCount(1, $porGrupo['IV']);
        self::assertSame('padre', $porGrupo['V'][0]['tipo']);
        self::assertCount(3, $porGrupo['V']);
        // 8000 - 500 - 150 - 200 - 800 = 6350
        self::assertSame(6350, $porGrupo['VIII'][0]['total_cents']);
        self::assertSame('IX. Saldo en las c/c personales', $porGrupo['IX'][0]['etiqueta']);
    }

    /**
     * @param list<int> $personas
     * @return array{codigo:string,etiqueta:string,grupo:string,total_cents:int,personas_cents:list<int>}
     */
    private static function linea(string $codigo, string $grupo, int $total, array $personas): array
    {
        return [
            'codigo' => $codigo,
            'etiqueta' => $codigo,
            'grupo' => $grupo,
            'total_cents' => $total,
            'personas_cents' => $personas,
        ];
    }
}
