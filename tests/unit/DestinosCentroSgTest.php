<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\plan\domain\services\DestinosCentroSg;

final class DestinosCentroSgTest extends TestCase
{
    public function testSolo42a54SonVariables(): void
    {
        $this->assertFalse(DestinosCentroSg::esVariable('41'));
        $this->assertTrue(DestinosCentroSg::esVariable('42'));
        $this->assertTrue(DestinosCentroSg::esVariable('54'));
        $this->assertFalse(DestinosCentroSg::esVariable('55'));
        $this->assertFalse(DestinosCentroSg::esVariable('71'));
    }

    public function testSiguienteSaltaLosUsados(): void
    {
        $this->assertSame('42', DestinosCentroSg::siguiente([]));
        $this->assertSame('47', DestinosCentroSg::siguiente(['42', '43', '44', '45', '46']));
        $this->assertNull(DestinosCentroSg::siguiente(array_map('strval', range(42, 54))));
    }

    public function testAplicarOcultaLosQueNoTienenNombre(): void
    {
        $conceptos = [
            ['codigo' => '41', 'cuenta' => 'G', 'nombre' => 'Necesidades generales', 'descripcion' => 'x', 'etiqueta' => '41 x'],
            ['codigo' => '42', 'cuenta' => 'G', 'nombre' => '42', 'descripcion' => '42', 'etiqueta' => '42 42'],
            ['codigo' => '47', 'cuenta' => 'G', 'nombre' => '47', 'descripcion' => '47', 'etiqueta' => '47 47'],
            ['codigo' => '11', 'cuenta' => 'G', 'nombre' => 'Aportaciones ordinarias', 'descripcion' => 'x', 'etiqueta' => '11 x'],
        ];
        $out = DestinosCentroSg::aplicar($conceptos, ['42' => 'CARF']);
        $codigos = array_column($out, 'codigo');
        $this->assertSame(['41', '42', '11'], $codigos);
        $this->assertSame('CARF', $out[1]['nombre']);
        $this->assertSame('42 CARF', $out[1]['etiqueta']);
    }

    public function testAplicarNoTocaOtroPlan(): void
    {
        $conceptos = [
            ['codigo' => '42', 'cuenta' => 'G', 'nombre' => '42', 'descripcion' => '42', 'etiqueta' => '42 42'],
        ];
        $this->assertSame($conceptos, DestinosCentroSg::aplicar($conceptos, null));
    }
}
