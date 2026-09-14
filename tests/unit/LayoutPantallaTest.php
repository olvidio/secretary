<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\value_objects\LayoutPantalla;

final class LayoutPantallaTest extends TestCase
{
    public function testAceptaExcelYBurger(): void
    {
        self::assertSame('excel', LayoutPantalla::porDefecto()->valor);
        self::assertSame('burger', (new LayoutPantalla('burger'))->valor);
        self::assertSame('excel', LayoutPantalla::desde('')->valor);
    }

    public function testRechazaValorDesconocido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LayoutPantalla('pills');
    }
}
