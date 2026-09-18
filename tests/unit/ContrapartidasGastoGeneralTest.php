<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\apuntes\domain\services\ContrapartidasGastoGeneral;

final class ContrapartidasGastoGeneralTest extends TestCase
{
    public function testGastoGConInicialesGeneraCuatroLineas(): void
    {
        $lineas = (new ContrapartidasGastoGeneral())->lineas(
            'G',
            'C',
            '201',
            'gasto',
            'ac',
            'agua',
        );

        self::assertNotNull($lineas);
        self::assertCount(4, $lineas);
        self::assertSame(['P', 'A', '111', null], $this->tuple($lineas[0]));
        self::assertSame(['P', 'A', '211', 'agua'], $this->tuple($lineas[1]));
        self::assertSame(['G', 'A', '11', 'agua'], $this->tuple($lineas[2]));
        self::assertSame(['G', 'C', '201', 'agua'], $this->tuple($lineas[3]));
    }

    public function testNoExpandeSinInicialesNiEnPNiIngreso(): void
    {
        $svc = new ContrapartidasGastoGeneral();
        self::assertNull($svc->lineas('G', 'A', '201', 'gasto', '', null));
        self::assertNull($svc->lineas('P', 'A', '22', 'gasto', 'ac', null));
        self::assertNull($svc->lineas('G', 'A', '11', 'ingreso', 'ac', null));
    }

    /** @param array{cuenta:string,origen:string,concepto_codigo:string,observaciones:?string} $linea */
    private function tuple(array $linea): array
    {
        return [$linea['cuenta'], $linea['origen'], $linea['concepto_codigo'], $linea['observaciones']];
    }
}
