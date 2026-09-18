<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\disponible\domain\services\RepartidorLabores;
use src\disponible\domain\services\TramosDesgravacion;

final class RepartidorLaboresTest extends TestCase
{
    public function testTresPersonasLlenanElPrimerTramoAntesQueUnaSola(): void
    {
        $tramos = TramosDesgravacion::porDefecto();
        $partidas = [
            ['codigo' => '73', 'etiqueta' => 'Proas', 'desgrava' => true, 'orden' => 10, 'hueco_cents' => 100000],
            ['codigo' => '74', 'etiqueta' => 'Prelatura', 'desgrava' => false, 'orden' => 20, 'hueco_cents' => 100000],
        ];
        $tres = RepartidorLabores::repartir(
            [
                ['id' => 1, 'disponible_cents' => 10000, 'puede_desgravar' => true, 'ya_desgravado_cents' => 0],
                ['id' => 2, 'disponible_cents' => 10000, 'puede_desgravar' => true, 'ya_desgravado_cents' => 0],
                ['id' => 3, 'disponible_cents' => 10000, 'puede_desgravar' => true, 'ya_desgravado_cents' => 0],
            ],
            $partidas,
            $tramos,
        );
        $una = RepartidorLabores::repartir(
            [
                ['id' => 9, 'disponible_cents' => 30000, 'puede_desgravar' => true, 'ya_desgravado_cents' => 0],
            ],
            $partidas,
            $tramos,
        );
        $en73Tres = 0;
        foreach ($tres['lineas'] as $l) {
            if ($l['codigo'] === '73') {
                $en73Tres += $l['cents'];
                self::assertSame(10000, $l['cents']);
            }
        }
        $en73Una = 0;
        foreach ($una['lineas'] as $l) {
            if ($l['codigo'] === '73') {
                $en73Una += $l['cents'];
            }
        }
        self::assertSame(30000, $en73Tres);
        self::assertSame(30000, $en73Una);
        self::assertCount(3, $tres['lineas']);
        self::assertCount(1, $una['lineas']);
    }

    public function testSiElPresupuestoDesgravaSeAgotaElRestoVaAPartidasQueNoDesgravan(): void
    {
        $r = RepartidorLabores::repartir(
            [
                ['id' => 9, 'disponible_cents' => 30000, 'puede_desgravar' => true, 'ya_desgravado_cents' => 0],
            ],
            [
                ['codigo' => '73', 'etiqueta' => 'Proas', 'desgrava' => true, 'orden' => 10, 'hueco_cents' => 25000],
                ['codigo' => '74', 'etiqueta' => 'Prelatura', 'desgrava' => false, 'orden' => 20, 'hueco_cents' => 80000],
            ],
            TramosDesgravacion::porDefecto(),
        );
        $por = [];
        foreach ($r['lineas'] as $l) {
            $por[$l['codigo']] = $l['cents'];
        }
        self::assertSame(25000, $por['73']);
        self::assertSame(5000, $por['74']);
    }

    public function testQuienNoDesgravaVaTodoAPartidasQueNoDesgravan(): void
    {
        $r = RepartidorLabores::repartir(
            [
                ['id' => 1, 'disponible_cents' => 40000, 'puede_desgravar' => false, 'ya_desgravado_cents' => 0],
            ],
            [
                ['codigo' => '73', 'etiqueta' => 'Proas', 'desgrava' => true, 'orden' => 10, 'hueco_cents' => 80000],
                ['codigo' => '74', 'etiqueta' => 'Prelatura', 'desgrava' => false, 'orden' => 20, 'hueco_cents' => 80000],
            ],
            TramosDesgravacion::porDefecto(),
        );
        self::assertCount(1, $r['lineas']);
        self::assertSame('74', $r['lineas'][0]['codigo']);
        self::assertSame(40000, $r['lineas'][0]['cents']);
        self::assertStringContainsString('74', $r['textos'][0]['texto']);
    }

    public function testElTopeDeLaBaseDejaElExcesoEnPartidasQueNoDesgravan(): void
    {
        $r = RepartidorLabores::repartir(
            [
                [
                    'id' => 1,
                    'disponible_cents' => 100000,
                    'puede_desgravar' => true,
                    'ya_desgravado_cents' => 0,
                    'tope_cents' => 25000,
                ],
            ],
            [
                ['codigo' => '73', 'etiqueta' => 'Proas', 'desgrava' => true, 'orden' => 10, 'hueco_cents' => 200000],
                ['codigo' => '74', 'etiqueta' => 'Prelatura', 'desgrava' => false, 'orden' => 20, 'hueco_cents' => 200000],
            ],
            TramosDesgravacion::porDefecto(),
        );
        $por = [];
        foreach ($r['lineas'] as $l) {
            $por[$l['codigo']] = $l['cents'];
        }
        self::assertSame(25000, $por['73']);
        self::assertSame(75000, $por['74']);
    }

    public function testSiElUltimoTramoTieneHastaElExcesoNoDesgrava(): void
    {
        $r = RepartidorLabores::repartir(
            [
                ['id' => 1, 'disponible_cents' => 50000, 'puede_desgravar' => true, 'ya_desgravado_cents' => 0],
            ],
            [
                ['codigo' => '73', 'etiqueta' => 'Proas', 'desgrava' => true, 'orden' => 10, 'hueco_cents' => 200000],
                ['codigo' => '74', 'etiqueta' => 'Prelatura', 'desgrava' => false, 'orden' => 20, 'hueco_cents' => 200000],
            ],
            [
                ['hasta_cents' => 25000, 'porcentaje' => 80],
                ['hasta_cents' => 40000, 'porcentaje' => 40],
            ],
        );
        $por = [];
        foreach ($r['lineas'] as $l) {
            $por[$l['codigo']] = $l['cents'];
        }
        self::assertSame(40000, $por['73']);
        self::assertSame(10000, $por['74']);
    }

    public function testTextoDeVariasPartidas(): void
    {
        $t = RepartidorLabores::texto([
            ['persona_id' => 1, 'codigo' => '73', 'etiqueta' => 'Fundació Proas', 'cents' => 230000],
            ['persona_id' => 1, 'codigo' => '74', 'etiqueta' => 'Prelatura', 'cents' => 40000],
        ]);
        self::assertSame(
            'Deberías ingresar 2.300,00 € en 73 Fundació Proas y 400,00 € en 74 Prelatura.',
            $t
        );
    }
}
