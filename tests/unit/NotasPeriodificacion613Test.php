<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\informes\domain\services\NotasPeriodificacion613;

final class NotasPeriodificacion613Test extends TestCase
{
    public function testImputadoAntesDelCorteConContrapartidaDespues(): void
    {
        $r = NotasPeriodificacion613::armar([[
            'libro' => 'P', 'imputacion' => '2026-10-31', 'operacion' => '2026-11-04', 'iniciales' => 'ac',
            'glosa' => 'per el club', 'codigo' => '211', 'nombre' => 'Vivienda general', 'cents' => 150000,
        ]], '2026-10-31');

        self::assertSame(['P'], array_keys($r));
        self::assertStringContainsString('AC · 211 Vivienda general · per el club: 1.500,00 € contados al 31/10/2026', $r['P']['notas'][0]);
        self::assertSame(
            'Imputado a este período con contrapartida posterior: AC 1.500,00 € (211, per el club; se anota el 04/11/2026).',
            $r['P']['observaciones'],
        );
    }

    public function testAnotadoAntesYContadoDespuesVaEnSuLibro(): void
    {
        $r = NotasPeriodificacion613::armar([[
            'libro' => 'G', 'imputacion' => '2026-11-30', 'operacion' => '2026-10-20', 'iniciales' => '',
            'glosa' => '', 'codigo' => '202', 'nombre' => 'Luz', 'cents' => 8000,
        ]], '2026-10-31');

        self::assertSame(['G'], array_keys($r));
        self::assertStringContainsString('anotados el 20/10/2026', $r['G']['notas'][0]);
        self::assertStringStartsWith('Anotado en este período y contado en uno posterior:', $r['G']['observaciones']);
    }

    public function testSinFilasSinNotas(): void
    {
        self::assertSame([], NotasPeriodificacion613::armar([], '2026-10-31'));
    }
}
