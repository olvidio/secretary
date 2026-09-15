<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\disponible\domain\services\ConsumidorAsignacionesRemesa;

final class ConsumidorAsignacionesRemesaTest extends TestCase
{
    public function testRestaLasSieteYaConfirmadasYDejaElResto(): void
    {
        $r = ConsumidorAsignacionesRemesa::aplicar(
            [
                ['codigo_maestro' => '111', 'importe_cents' => 500000, 'tipo' => 'ingreso', 'cuenta_id' => 1],
                ['codigo_maestro' => '73', 'importe_cents' => 250000, 'tipo' => 'gasto', 'cuenta_id' => 73],
            ],
            [
                ['id' => 9, 'codigo_maestro' => '73', 'pendiente_cents' => 200000],
            ],
        );
        self::assertCount(1, $r['consumos']);
        self::assertSame(200000, $r['consumos'][0]['importe_cents']);
        $por = [];
        foreach ($r['lineas'] as $l) {
            $por[$l['codigo_maestro']] = $l['importe_cents'];
        }
        self::assertSame(500000, $por['111']);
        self::assertSame(50000, $por['73']);
    }
}
