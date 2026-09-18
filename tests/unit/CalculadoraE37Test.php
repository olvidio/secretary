<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\informes\domain\services\CalculadoraE37;
use src\shared\domain\value_objects\Dinero;

final class CalculadoraE37Test extends TestCase
{
    public function testViviendaVaAGastosYASuColumna(): void
    {
        $celdas = CalculadoraE37::celdasDeMovimiento('211', new Dinero('1500.00'));
        self::assertSame('1500.00', $celdas['vivienda']->toString());
        self::assertSame('1500.00', $celdas['gastos']->toString());
        self::assertArrayNotHasKey('ingresos', $celdas);
        self::assertArrayNotHasKey('disponible', $celdas);
    }

    public function testIngresoSoloEnColumnaIngresos(): void
    {
        $celdas = CalculadoraE37::celdasDeMovimiento('111', new Dinero('100.00'));
        self::assertSame(['ingresos'], array_keys($celdas));
        self::assertSame('100.00', $celdas['ingresos']->toString());
    }

    public function testAyudaFamiliarNoSumaEnGastos(): void
    {
        $celdas = CalculadoraE37::celdasDeMovimiento('4', new Dinero('50.00'));
        self::assertSame('50.00', $celdas['ay_fam']->toString());
        self::assertArrayNotHasKey('gastos', $celdas);
    }

    public function testColumnasHojaCubrenLasClavesDeTotales(): void
    {
        $claves = array_column(CalculadoraE37::columnasHoja(), 'clave');
        self::assertSame([
            'ingresos', 'gastos', 'vivienda', 'ordinarios', 'ropa', 'ca_crt',
            'medicos', 'coche', 'estudios', 'obl_econ', 'disponible', 'ay_fam',
            'at_lab', 'nec_sede', 'lab_ap', 'saldo_final', 'saldo_cc',
        ], $claves);
    }

    public function testAvisoSaldoCcNegativoExplicaElRetraso(): void
    {
        $t = CalculadoraE37::avisoSaldoCcNegativo();
        self::assertStringContainsString('negativo', $t);
        self::assertStringContainsString('mes siguiente', $t);
    }
}
