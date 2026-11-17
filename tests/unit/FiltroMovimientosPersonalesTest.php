<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\personal\domain\services\FiltroMovimientosPersonales;

final class FiltroMovimientosPersonalesTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function filas(): array
    {
        return [
            ['id' => 1, 'sentido' => 'gasto', 'cents' => 2350, 'nota' => 'Farmacia López', 'categoria' => 'Médicos', 'categoria_id' => 25, 'tesoreria' => 'BANCO', 'tesoreria_origen' => null, 'tesoreria_destino' => null],
            ['id' => 2, 'sentido' => 'gasto', 'cents' => 6000, 'nota' => 'Dentista', 'categoria' => 'Médicos', 'categoria_id' => 250, 'tesoreria' => 'CAJA', 'tesoreria_origen' => null, 'tesoreria_destino' => null],
            ['id' => 3, 'sentido' => 'ingreso', 'cents' => 150000, 'nota' => 'Nómina', 'categoria' => 'Trabajo', 'categoria_id' => 11, 'tesoreria' => 'BANCO', 'tesoreria_origen' => null, 'tesoreria_destino' => null],
            ['id' => 4, 'sentido' => 'traspaso', 'cents' => 10000, 'nota' => null, 'categoria' => null, 'categoria_id' => null, 'tesoreria' => null, 'tesoreria_origen' => 'BANCO', 'tesoreria_destino' => 'CAJA'],
        ];
    }

    /** @param list<array<string, mixed>> $filas @return list<int> */
    private function ids(array $filas): array
    {
        return array_map(static fn (array $f): int => (int) $f['id'], $filas);
    }

    public function testSinCriteriosPasaTodo(): void
    {
        self::assertSame([1, 2, 3, 4], $this->ids((new FiltroMovimientosPersonales())->aplicar($this->filas())));
    }

    public function testCategoriaConSubcuentas(): void
    {
        $f = new FiltroMovimientosPersonales(categoriaIds: [25, 250]);
        self::assertSame([1, 2], $this->ids($f->aplicar($this->filas())));
    }

    public function testSentidoYTesoreriaIncluyeTraspasos(): void
    {
        self::assertSame([3], $this->ids((new FiltroMovimientosPersonales(sentido: 'ingreso'))->aplicar($this->filas())));
        self::assertSame([2, 4], $this->ids((new FiltroMovimientosPersonales(tesoreria: 'caja'))->aplicar($this->filas())));
    }

    public function testTextoSinAcentosNiMayusculasYPorPalabras(): void
    {
        self::assertSame([1, 2], $this->ids((new FiltroMovimientosPersonales(texto: 'medicos'))->aplicar($this->filas())));
        self::assertSame([1], $this->ids((new FiltroMovimientosPersonales(texto: 'FARMACIA medic'))->aplicar($this->filas())));
        self::assertSame([3], $this->ids((new FiltroMovimientosPersonales(texto: 'nomina'))->aplicar($this->filas())));
    }

    public function testRangoDeImporte(): void
    {
        $f = new FiltroMovimientosPersonales(minCents: 2350, maxCents: 10000);
        self::assertSame([1, 2, 4], $this->ids($f->aplicar($this->filas())));
    }

    public function testTotalesNoSumanTraspasos(): void
    {
        self::assertSame(
            ['ingresos' => 150000, 'gastos' => 8350, 'neto' => 141650, 'numero' => 4],
            FiltroMovimientosPersonales::totales($this->filas()),
        );
    }
}
