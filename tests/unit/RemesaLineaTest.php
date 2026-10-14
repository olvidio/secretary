<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\remesas\domain\entity\RemesaLinea;

final class RemesaLineaTest extends TestCase
{
    public function testToArrayExponePlantillasYGenerales(): void
    {
        $linea = new RemesaLinea(null, null, '21', 0, [[
            'codigo' => '21',
            'nombre' => 'Vivienda',
            'cents' => 0,
            'generales' => [['concepto' => '204', 'cents' => 1000]],
            'plantillas' => [['plantilla_id' => 3, 'cents' => 2500, 'nombre' => 'Club']],
        ]]);

        $out = $linea->toArray();

        self::assertSame('204', $out['detalle'][0]['generales'][0]['concepto']);
        self::assertSame('10,00', $out['detalle'][0]['generales'][0]['importe_es']);
        self::assertSame(3, $out['detalle'][0]['plantillas'][0]['plantilla_id']);
        self::assertSame('Club', $out['detalle'][0]['plantillas'][0]['nombre']);
        self::assertSame('25,00', $out['detalle'][0]['plantillas'][0]['importe_es']);
    }

    public function testImporteSinGeneralesRestaLoMarcadoComoGeneral(): void
    {
        $linea = new RemesaLinea(null, null, '211', 5000, [[
            'codigo' => '211',
            'nombre' => 'Vivienda general',
            'cents' => 5000,
            'generales' => [['concepto' => '208', 'cents' => 5000]],
        ]]);
        self::assertSame(0, $linea->importeCentsSinGenerales());

        $mixta = new RemesaLinea(null, null, '22', 9000, [
            [
                'codigo' => '22',
                'nombre' => 'Ordinarios',
                'cents' => 4000,
            ],
            [
                'codigo' => '22.gas',
                'nombre' => 'Gas',
                'cents' => 5000,
                'generales' => [['concepto' => '208', 'cents' => 5000]],
            ],
        ]);
        self::assertSame(4000, $mixta->importeCentsSinGenerales());
    }
}
