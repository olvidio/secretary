<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\cierre\domain\services\ConstructorAsientoApertura;

final class ConstructorAsientoAperturaTest extends TestCase
{
    public function testCuadraConPlugPatrimonio(): void
    {
        $saldos = [
            ['id' => 1, 'libro' => 'G', 'codigo' => 'CAJA.1/G', 'codigo_maestro' => 'CAJA', 'tipo' => 'tesoreria', 'persona_id' => null, 'cuenta_fisica_id' => 1, 'saldo_cents' => 10000],
            ['id' => 2, 'libro' => 'G', 'codigo' => 'CC.AC', 'codigo_maestro' => '9', 'tipo' => 'personal', 'persona_id' => 5, 'cuenta_fisica_id' => null, 'saldo_cents' => -2500],
        ];
        $movs = ConstructorAsientoApertura::movimientos('G', $saldos, 99);
        self::assertCount(3, $movs);
        $debe = 0;
        $haber = 0;
        foreach ($movs as $m) {
            $debe += $m->debeCents;
            $haber += $m->haberCents;
        }
        self::assertSame($debe, $haber);
        self::assertSame(10000, $debe);
    }

    public function testSinPatrimonioSiPlugCero(): void
    {
        $saldos = [
            ['id' => 1, 'libro' => 'P', 'codigo' => 'CAJA.1/P', 'codigo_maestro' => 'CAJA', 'tipo' => 'tesoreria', 'persona_id' => null, 'cuenta_fisica_id' => 1, 'saldo_cents' => 5000],
            ['id' => 2, 'libro' => 'P', 'codigo' => 'BANCO.1/P', 'codigo_maestro' => 'BANCO', 'tipo' => 'tesoreria', 'persona_id' => null, 'cuenta_fisica_id' => 2, 'saldo_cents' => -5000],
        ];
        $movs = ConstructorAsientoApertura::movimientos('P', $saldos, 99);
        self::assertCount(2, $movs);
        $debe = array_sum(array_map(static fn ($m) => $m->debeCents, $movs));
        $haber = array_sum(array_map(static fn ($m) => $m->haberCents, $movs));
        self::assertSame($debe, $haber);
    }

    public function testNoArrastraIngresoNiG32(): void
    {
        $saldos = [
            ['id' => 1, 'libro' => 'G', 'codigo' => '32', 'codigo_maestro' => '32', 'tipo' => 'patrimonio', 'persona_id' => null, 'cuenta_fisica_id' => null, 'saldo_cents' => 99999],
            ['id' => 2, 'libro' => 'G', 'codigo' => '201', 'codigo_maestro' => '201', 'tipo' => 'gasto', 'persona_id' => null, 'cuenta_fisica_id' => null, 'saldo_cents' => 5000],
        ];
        $movs = ConstructorAsientoApertura::movimientos('G', $saldos, 99);
        self::assertSame([], $movs);
    }

    public function testArrastraPuentePeriodificacionNoG41(): void
    {
        $saldos = [
            ['id' => 1, 'libro' => 'G', 'codigo' => 'PUENTE.PERIODIFICACION', 'codigo_maestro' => 'PERIODIFICACION', 'tipo' => 'puente', 'persona_id' => null, 'cuenta_fisica_id' => null, 'saldo_cents' => -10000],
            ['id' => 2, 'libro' => 'G', 'codigo' => '41', 'codigo_maestro' => '41', 'tipo' => 'puente', 'persona_id' => null, 'cuenta_fisica_id' => null, 'saldo_cents' => 4000],
            ['id' => 3, 'libro' => 'G', 'codigo' => 'CAJA.1/G', 'codigo_maestro' => 'CAJA', 'tipo' => 'tesoreria', 'persona_id' => null, 'cuenta_fisica_id' => 1, 'saldo_cents' => 10000],
        ];
        $movs = ConstructorAsientoApertura::movimientos('G', $saldos, 99);
        $cuentas = array_map(static fn ($m) => $m->cuentaId, $movs);
        self::assertContains(1, $cuentas);
        self::assertContains(3, $cuentas);
        self::assertNotContains(2, $cuentas);
    }
}
