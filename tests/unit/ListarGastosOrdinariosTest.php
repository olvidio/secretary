<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\personal\application\ListarGastosOrdinarios;

final class ListarGastosOrdinariosTest extends TestCase
{
    public function testSoloLaCuenta22YSusSubcuentas(): void
    {
        $datos = ListarGastosOrdinarios::lineasDe([
            $this->fila('2026-09-02', '22', 'gasto', '10.50', 'Pan'),
            $this->fila('2026-09-01', '22.comida', 'gasto', '4.00', 'Mercado'),
            $this->fila('2026-09-03', '22', 'ingreso', '2.00', 'Devolución'),
            $this->fila('2026-09-04', '22.pendiente', 'gasto', '9.00', 'Sin categoría'),
            $this->fila('2026-09-05', '21', 'gasto', '8.00', 'Vivienda'),
            $this->fila('2026-09-06', '22', 'traspaso', '1.00', 'Caja'),
        ]);

        self::assertSame(['2026-09-01', '2026-09-02', '2026-09-03'], array_column($datos['lineas'], 'fecha'));
        self::assertSame('22.comida', $datos['lineas'][0]['categoria_codigo']);
        self::assertSame('-2,00', $datos['lineas'][2]['importe_es']);
        self::assertSame('12,50', $datos['total_es']);
    }

    public function testPendienteNoEsOrdinario(): void
    {
        self::assertTrue(ListarGastosOrdinarios::esCuentaOrdinarios('22'));
        self::assertTrue(ListarGastosOrdinarios::esCuentaOrdinarios('22.comida'));
        self::assertFalse(ListarGastosOrdinarios::esCuentaOrdinarios('22.pendiente'));
        self::assertFalse(ListarGastosOrdinarios::esCuentaOrdinarios('221'));
    }

    /** @return array<string, string> */
    private function fila(string $fecha, string $codigo, string $sentido, string $cantidad, string $nota): array
    {
        return [
            'fecha' => $fecha,
            'categoria_codigo' => $codigo,
            'sentido' => $sentido,
            'cantidad' => $cantidad,
            'nota' => $nota,
            'categoria' => $codigo,
        ];
    }
}
