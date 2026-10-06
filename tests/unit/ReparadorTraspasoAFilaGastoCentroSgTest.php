<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\asientos\domain\services\ReparadorTraspasoAFilaGastoCentroSg;

final class ReparadorTraspasoAFilaGastoCentroSgTest extends TestCase
{
    public function testPatron41(): void
    {
        $r = ReparadorTraspasoAFilaGastoCentroSg::proponer([
            ['codigo_maestro' => 'CAJA', 'tipo' => 'tesoreria', 'codigo' => 'CAJA.1/G', 'debe' => 5000, 'haber' => 0, 'cuenta_id' => 10],
            ['codigo_maestro' => 'BANCO', 'tipo' => 'tesoreria', 'codigo' => 'BANCO.1/G', 'debe' => 0, 'haber' => 5000, 'cuenta_id' => 11],
        ]);
        self::assertNotNull($r);
        self::assertSame('41', $r['concepto_codigo']);
        self::assertSame(5000, $r['importe']);
        self::assertSame(10, $r['caja_cuenta_id']);
    }

    public function testPatron42(): void
    {
        $r = ReparadorTraspasoAFilaGastoCentroSg::proponer([
            ['codigo_maestro' => 'BANCO', 'tipo' => 'tesoreria', 'codigo' => 'BANCO.1/G', 'debe' => 8000, 'haber' => 0, 'cuenta_id' => 11],
            ['codigo_maestro' => 'CAJA', 'tipo' => 'tesoreria', 'codigo' => 'CAJA.1/G', 'debe' => 0, 'haber' => 8000, 'cuenta_id' => 10],
        ]);
        self::assertNotNull($r);
        self::assertSame('42', $r['concepto_codigo']);
    }

    public function testRechazaSiHayGasto(): void
    {
        self::assertNull(ReparadorTraspasoAFilaGastoCentroSg::proponer([
            ['codigo_maestro' => '41', 'tipo' => 'gasto', 'codigo' => '41', 'debe' => 100, 'haber' => 0, 'cuenta_id' => 1],
            ['codigo_maestro' => 'CAJA', 'tipo' => 'tesoreria', 'codigo' => 'CAJA.1/G', 'debe' => 0, 'haber' => 100, 'cuenta_id' => 10],
        ]));
    }
}
