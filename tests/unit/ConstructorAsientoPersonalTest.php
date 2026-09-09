<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\personal\domain\services\ConstructorAsientoPersonal;

final class ConstructorAsientoPersonalTest extends TestCase
{
    public function testGastoCargaCategoriaYAbonaTesoreria(): void
    {
        $a = ConstructorAsientoPersonal::movimiento(
            1,
            9,
            new DateTimeImmutable('2026-03-15'),
            'Pan',
            'gasto',
            21,
            50,
            1250,
            '22',
        );
        self::assertSame('X', $a->libro);
        self::assertSame(9, $a->personaId);
        self::assertSame(1250, $a->movimientos[0]->debeCents);
        self::assertSame(21, $a->movimientos[0]->cuentaId);
        self::assertSame(1250, $a->movimientos[1]->haberCents);
        self::assertSame(50, $a->movimientos[1]->cuentaId);
    }

    public function testIngresoCargaTesoreriaYAbonaCategoria(): void
    {
        $a = ConstructorAsientoPersonal::movimiento(
            1,
            9,
            new DateTimeImmutable('2026-03-15'),
            null,
            'ingreso',
            11,
            50,
            5000,
            '111',
        );
        self::assertSame(50, $a->movimientos[0]->cuentaId);
        self::assertSame(5000, $a->movimientos[0]->debeCents);
        self::assertSame(11, $a->movimientos[1]->cuentaId);
        self::assertSame(5000, $a->movimientos[1]->haberCents);
    }

    public function testTraspasoNoPermiteMismaTesoreria(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConstructorAsientoPersonal::traspaso(
            1,
            9,
            new DateTimeImmutable('2026-03-15'),
            null,
            50,
            50,
            100,
        );
    }
}
