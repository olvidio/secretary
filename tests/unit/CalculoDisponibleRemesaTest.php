<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\remesas\domain\services\CalculoDisponibleRemesa;

final class CalculoDisponibleRemesaTest extends TestCase
{
    public function testElDisponibleEsElSaldoMenosElRemanente(): void
    {
        self::assertSame(80000, CalculoDisponibleRemesa::cents(100000, 20000));
    }

    public function testSinRemanenteSeEnviaTodoElSaldo(): void
    {
        self::assertSame(100000, CalculoDisponibleRemesa::cents(100000, 0));
    }

    public function testSiElRemanenteCubreElSaldoNoSeEnviaNada(): void
    {
        self::assertSame(0, CalculoDisponibleRemesa::cents(15000, 20000));
        self::assertSame(0, CalculoDisponibleRemesa::cents(20000, 20000));
    }

    public function testUnSaldoNegativoNoGeneraDisponible(): void
    {
        self::assertSame(0, CalculoDisponibleRemesa::cents(-500, 1000));
    }
}
