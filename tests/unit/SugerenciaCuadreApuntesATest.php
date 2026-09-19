<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\apuntes\domain\services\SugerenciaCuadreApuntesA;

final class SugerenciaCuadreApuntesATest extends TestCase
{
    public function testSugiere111SiSoloGastosEnFecha(): void
    {
        $s = SugerenciaCuadreApuntesA::sugerir(
            cuadrado: false,
            cuadradoAntes: true,
            hayApuntesFecha: true,
            soloGastosFecha: true,
            saldoFecha: 5000,
            saldoTotal: 5000,
            fecha: '2026-03-15',
            apuntesG: [],
            naturalezasG: [],
            viviendaAportaGenerales: true,
        );
        self::assertNotNull($s);
        self::assertSame('111', $s['concepto_codigo']);
        self::assertSame('solo_gastos_fecha', $s['motivo']);
    }

    public function testSugiere211SiHayG11YFaltaGastoP(): void
    {
        $s = SugerenciaCuadreApuntesA::sugerir(
            cuadrado: false,
            cuadradoAntes: false,
            hayApuntesFecha: false,
            soloGastosFecha: false,
            saldoFecha: 0,
            saldoTotal: -65000,
            fecha: '2026-02-28',
            apuntesG: [[
                'cuenta' => 'G',
                'origen' => 'A',
                'concepto_codigo' => '11',
                'iniciales' => 'qr',
                'fecha' => '2026-02-28',
                'cantidad' => '650.00',
            ]],
            naturalezasG: ['11' => 'ingreso'],
            viviendaAportaGenerales: true,
        );
        self::assertNotNull($s);
        self::assertSame('211', $s['concepto_codigo']);
        self::assertSame('650.00', $s['cantidad']);
        self::assertSame('saldo_negativo_vivienda', $s['motivo']);
    }

    public function testSugiere212SiNoAportaYSinParG11(): void
    {
        $s = SugerenciaCuadreApuntesA::sugerir(
            cuadrado: false,
            cuadradoAntes: false,
            hayApuntesFecha: false,
            soloGastosFecha: false,
            saldoFecha: 0,
            saldoTotal: -10000,
            fecha: '2026-02-28',
            apuntesG: [],
            naturalezasG: [],
            viviendaAportaGenerales: false,
        );
        self::assertNotNull($s);
        self::assertSame('212', $s['concepto_codigo']);
    }

    public function testSugiere211PorDefectoSiAportaSinParG(): void
    {
        $s = SugerenciaCuadreApuntesA::sugerir(
            cuadrado: false,
            cuadradoAntes: false,
            hayApuntesFecha: false,
            soloGastosFecha: false,
            saldoFecha: 0,
            saldoTotal: -10000,
            fecha: '2026-02-28',
            apuntesG: [],
            naturalezasG: [],
            viviendaAportaGenerales: true,
        );
        self::assertNotNull($s);
        self::assertSame('211', $s['concepto_codigo']);
    }

    public function testNecesidadesSugiere6(): void
    {
        $concepto = SugerenciaCuadreApuntesA::conceptoViviendaFaltante(
            5000,
            '2026-02-28',
            [],
            [],
            false,
            'necesidades',
        );
        self::assertSame('6', $concepto);
    }
}
