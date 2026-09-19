<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\apuntes\domain\services\SaldoCuadreApuntes;

final class SaldoCuadreApuntesTest extends TestCase
{
    public function testAcumulaPorPersonaYOrigen(): void
    {
        $naturalezas = ['22' => 'gasto', '111' => 'ingreso', '201' => 'gasto'];
        $apuntes = [
            ['cuenta' => 'P', 'origen' => 'A', 'iniciales' => 'aa', 'concepto_codigo' => '22', 'cantidad' => '10.00'],
            ['cuenta' => 'P', 'origen' => 'A', 'iniciales' => 'aa', 'concepto_codigo' => '111', 'cantidad' => '10.00'],
            ['cuenta' => 'P', 'origen' => 'C', 'iniciales' => 'bb', 'concepto_codigo' => '201', 'cantidad' => '5.00'],
            ['cuenta' => 'G', 'origen' => 'C', 'iniciales' => 'bb', 'concepto_codigo' => '201', 'cantidad' => '99.00'],
        ];
        $map = SaldoCuadreApuntes::porPersonaYOrigen(
            $apuntes,
            $naturalezas,
            ['aa' => 1, 'bb' => 2],
        );
        self::assertSame(0, $map[1]['A']);
        self::assertSame(500, $map[2]['C']);
        self::assertSame(0, $map[2]['B']);
        self::assertArrayNotHasKey(1, $map[2] ?? []);
    }
}
