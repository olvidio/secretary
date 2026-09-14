<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\remesas\domain\services\AgregadorRemesaPersonal;

final class AgregadorRemesaPersonalTest extends TestCase
{
    public function testAgregaPorMaestroEIgnoraTraspasoYTesoreria(): void
    {
        $gasto = new Cuenta(21, 1, 9, null, null, 'X', '22', 'Ordinarios', '', 'gasto', 'deudora', '22', true);
        $sub = new Cuenta(22, 1, 9, null, 21, 'X', '22.gas', 'Gas', '', 'gasto', 'deudora', '22', true);
        $ing = new Cuenta(11, 1, 9, null, null, 'X', '111', 'Trabajo', '', 'ingreso', 'acreedora', '111', true);
        $caja = new Cuenta(50, 1, 9, null, null, 'X', 'CAJA', 'Caja', '', 'tesoreria', 'deudora', 'CAJA', true);
        $banco = new Cuenta(51, 1, 9, null, null, 'X', 'BANCO', 'Banco', '', 'tesoreria', 'deudora', 'BANCO', true);
        $cuentas = [
            21 => $gasto,
            22 => $sub,
            11 => $ing,
            50 => $caja,
            51 => $banco,
        ];
        $asientos = [
            new Asiento(1, 1, 'X', 1, new DateTimeImmutable('2026-01-10'), 'Pan', 'normal', 'manual', 9, [
                new Movimiento(1, 1, 21, 9, 1250, 0),
                new Movimiento(2, 2, 50, 9, 0, 1250),
            ], '22'),
            new Asiento(2, 1, 'X', 2, new DateTimeImmutable('2026-01-11'), 'Gas', 'normal', 'manual', 9, [
                new Movimiento(3, 1, 22, 9, 4000, 0),
                new Movimiento(4, 2, 50, 9, 0, 4000),
            ], '22.gas'),
            new Asiento(3, 1, 'X', 3, new DateTimeImmutable('2026-01-12'), 'Nómina', 'normal', 'manual', 9, [
                new Movimiento(5, 1, 50, 9, 10000, 0),
                new Movimiento(6, 2, 11, 9, 0, 10000),
            ], '111'),
            new Asiento(4, 1, 'X', 4, new DateTimeImmutable('2026-01-13'), 'Traspaso', 'traspaso', 'manual', 9, [
                new Movimiento(7, 1, 51, 9, 2000, 0),
                new Movimiento(8, 2, 50, 9, 0, 2000),
            ]),
        ];
        $lineas = AgregadorRemesaPersonal::agregar($asientos, $cuentas);
        self::assertCount(2, $lineas);
        self::assertSame('111', $lineas[0]->codigoMaestro);
        self::assertSame(10000, $lineas[0]->importeCents);
        self::assertSame('22', $lineas[1]->codigoMaestro);
        self::assertSame(5250, $lineas[1]->importeCents);
        self::assertCount(2, $lineas[1]->detalle);
    }

    public function testIgnoraOtraContabilidad(): void
    {
        $otra = new Cuenta(80, 1, 9, null, null, 'X', 'OTRA.gasto', 'Otra contabilidad', '', 'gasto', 'deudora', 'OTRA', true);
        $banco = new Cuenta(51, 1, 9, null, null, 'X', 'BANCO', 'Banco', '', 'tesoreria', 'deudora', 'BANCO', true);
        $gasto = new Cuenta(21, 1, 9, null, null, 'X', '22', 'Ordinarios', '', 'gasto', 'deudora', '22', true);
        $lineas = AgregadorRemesaPersonal::agregar([
            new Asiento(1, 1, 'X', 1, new DateTimeImmutable('2026-01-10'), 'Fuera', 'normal', 'banco', 9, [
                new Movimiento(1, 1, 80, 9, 9900, 0),
                new Movimiento(2, 2, 51, 9, 0, 9900),
            ], 'OTRA.gasto'),
            new Asiento(2, 1, 'X', 2, new DateTimeImmutable('2026-01-11'), 'Pan', 'normal', 'banco', 9, [
                new Movimiento(3, 1, 21, 9, 1250, 0),
                new Movimiento(4, 2, 51, 9, 0, 1250),
            ], '22'),
        ], [80 => $otra, 51 => $banco, 21 => $gasto]);
        self::assertCount(1, $lineas);
        self::assertSame('22', $lineas[0]->codigoMaestro);
        self::assertSame(1250, $lineas[0]->importeCents);
    }

    public function testAgregaGeneralesEnDetalle(): void
    {
        $gasto = new Cuenta(21, 1, 9, null, null, 'X', '22', 'Ordinarios', '', 'gasto', 'deudora', '22', true);
        $sub = new Cuenta(22, 1, 9, null, 21, 'X', '22.gas', 'Gas', '', 'gasto', 'deudora', '22', true);
        $caja = new Cuenta(50, 1, 9, null, null, 'X', 'CAJA', 'Caja', '', 'tesoreria', 'deudora', 'CAJA', true);
        $cuentas = [21 => $gasto, 22 => $sub, 50 => $caja];
        $asientos = [
            new Asiento(1, 1, 'X', 1, new DateTimeImmutable('2026-01-10'), 'Gas casa', 'normal', 'manual', 9, [
                new Movimiento(1, 1, 22, 9, 4000, 0),
                new Movimiento(2, 2, 50, 9, 0, 4000),
            ], '22.gas', null, null, null, true, '204'),
        ];
        $lineas = AgregadorRemesaPersonal::agregar($asientos, $cuentas);
        self::assertCount(1, $lineas);
        self::assertSame('22', $lineas[0]->codigoMaestro);
        self::assertCount(1, $lineas[0]->detalle);
        self::assertSame([['concepto' => '204', 'cents' => 4000]], $lineas[0]->detalle[0]['generales'] ?? []);
    }
}
