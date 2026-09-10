<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\informes\domain\services\CuadreViviendaGenerales;

final class CuadreViviendaGeneralesTest extends TestCase
{
    public function testQuienAportaDebeCuadrar21Con11(): void
    {
        $r = (new CuadreViviendaGenerales())->ejecutar(
            [
                ['iniciales' => 'fb', 'origen' => 'A', 'cantidad' => '1000.00'],
                ['iniciales' => 'fb', 'origen' => 'A', 'cantidad' => '130.00'],
            ],
            [
                ['iniciales' => 'fb', 'origen' => 'A', 'cantidad' => '1000.00'],
            ],
            [
                ['iniciales' => 'fb', 'nombre' => 'Francesc Boix', 'aporta' => true],
                ['iniciales' => 'xx', 'nombre' => 'Agd', 'aporta' => false],
            ],
        );

        self::assertFalse($r['ok']);
        self::assertSame('1.130,00', $r['total_p21_es']);
        self::assertSame('1.000,00', $r['total_g11_es']);
        self::assertSame('130,00', $r['por_persona'][0]['diferencia_es']);
        self::assertFalse($r['por_persona'][0]['ok']);
    }

    public function testQuienNoAportaNoDebeTenerG11(): void
    {
        $r = (new CuadreViviendaGenerales())->ejecutar(
            [
                ['iniciales' => 'aa', 'origen' => 'A', 'cantidad' => '50.00'],
            ],
            [
                ['iniciales' => 'aa', 'origen' => 'A', 'cantidad' => '50.00'],
            ],
            [
                ['iniciales' => 'aa', 'nombre' => 'Ana Agd', 'aporta' => false],
            ],
        );

        self::assertFalse($r['ok']);
        self::assertSame('0,00', $r['total_p21_es']);
        self::assertNotSame([], $r['avisos']);
    }

    public function testCuadraSiLosImportesCoinciden(): void
    {
        $r = (new CuadreViviendaGenerales())->ejecutar(
            [['iniciales' => 'ac', 'origen' => 'A', 'cantidad' => '7500.00']],
            [['iniciales' => 'ac', 'origen' => 'A', 'cantidad' => '7500.00']],
            [['iniciales' => 'ac', 'nombre' => 'Antoni', 'aporta' => true]],
        );

        self::assertTrue($r['ok']);
        self::assertSame('7.500,00', $r['total_p21_es']);
    }
}
