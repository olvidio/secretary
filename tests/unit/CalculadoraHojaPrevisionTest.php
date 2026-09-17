<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\presupuestos\domain\services\CalculadoraHojaPrevision;

final class CalculadoraHojaPrevisionTest extends TestCase
{
    public function testSumaCodigosYPrefiereElGuardado(): void
    {
        $lineas = CalculadoraHojaPrevision::lineas(
            [
                ['codigo' => '24', 'etiqueta' => 'ca, crt, cv', 'codigos' => ['24']],
                ['codigo' => '111', 'etiqueta' => 'Trabajo', 'codigos' => ['111']],
            ],
            ['24' => 0, '111' => 60000],
            ['24' => 50000, '111' => 0],
            ['24' => 0, '111' => 0],
            ['24' => 45000],
            0,
            6,
            12,
        );
        self::assertSame('II', $lineas[0]['grupo']);
        self::assertSame('puntual', $lineas[0]['modo']);
        self::assertSame(50000, $lineas[0]['calculado_cents']);
        self::assertSame(45000, $lineas[0]['previsto_cents']);
        self::assertSame('lineal', $lineas[1]['modo']);
        self::assertSame(120000, $lineas[1]['calculado_cents']);
        self::assertNull($lineas[1]['previsto_cents']);
    }

    public function testSaldoUsaLaCcc(): void
    {
        $lineas = CalculadoraHojaPrevision::lineas(
            [['codigo' => '9', 'etiqueta' => 'c/c', 'codigos' => ['9']]],
            ['9' => 999],
            [],
            [],
            [],
            -2500,
            6,
            12,
        );
        self::assertSame(-2500, $lineas[0]['acumulado_cents']);
        self::assertSame(-2500, $lineas[0]['calculado_cents']);
    }
}
