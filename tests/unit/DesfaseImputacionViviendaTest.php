<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\informes\domain\services\DesfaseImputacionVivienda;

final class DesfaseImputacionViviendaTest extends TestCase
{
    /** El caso de AC: P/211 imputado al 31/10 (operación 4/11) y G/11 del 4/11 sin imputar. */
    public function testP211ImputadoYG11PosteriorEsDesfaseDeFechas(): void
    {
        $notas = DesfaseImputacionVivienda::notas(
            [['iniciales' => 'ac', 'cantidad' => '1500.00', 'fecha' => '2026-11-04', 'fecha_imputacion' => '2026-10-31']],
            [],
            [],
            [['iniciales' => 'AC', 'cantidad' => '1500.00', 'fecha' => '2026-11-04']],
            '2026-10-31',
        );

        self::assertCount(1, $notas['ac']);
        self::assertStringContainsString('al G/11 le falta la fecha de imputación 31/10/2026', $notas['ac'][0]);
    }

    public function testSinPartePosteriorPideImputarLaOtraMitad(): void
    {
        $notas = DesfaseImputacionVivienda::notas(
            [],
            [['iniciales' => 'fb', 'cantidad' => '200.00', 'fecha' => '2026-11-02', 'fecha_imputacion' => '2026-10-31']],
            [],
            [],
            '2026-10-31',
        );

        self::assertStringContainsString('el P/211 que lo acompaña', $notas['fb'][0]);
    }

    public function testApuntesNormalesOImputadosDentroDelPeriodoNoDanNota(): void
    {
        self::assertSame([], DesfaseImputacionVivienda::notas(
            [
                ['iniciales' => 'ac', 'cantidad' => '100.00', 'fecha' => '2026-10-15'],
                ['iniciales' => 'ac', 'cantidad' => '100.00', 'fecha' => '2026-10-20', 'fecha_imputacion' => '2026-09-30'],
            ],
            [],
            [],
            [],
            '2026-10-31',
        ));
    }
}
