<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\shared\domain\value_objects\Dinero;

final class DineroTest extends TestCase
{
    public function testFromCentsPositivo(): void
    {
        $d = Dinero::fromCents(12345);
        self::assertSame('123.45', $d->toString());
    }

    public function testFromCentsNegativo(): void
    {
        $d = Dinero::fromCents(-150);
        self::assertSame('-1.50', $d->toString());
    }

    public function testFromCentsCero(): void
    {
        $d = Dinero::fromCents(0);
        self::assertSame('0.00', $d->toString());
    }

    public function testToCentsPositivo(): void
    {
        self::assertSame(12345, (new Dinero('123.45'))->toCents());
    }

    public function testToCentsNegativo(): void
    {
        self::assertSame(-150, (new Dinero('-1.50'))->toCents());
    }

    public function testToCentsCero(): void
    {
        self::assertSame(0, Dinero::zero()->toCents());
    }

    public function testIdaYVueltaCents(): void
    {
        foreach ([0, 1, 99, 100, 12345, -1, -99, -12345] as $cents) {
            self::assertSame($cents, Dinero::fromCents($cents)->toCents());
        }
    }
}
