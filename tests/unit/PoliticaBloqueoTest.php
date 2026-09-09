<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\services\PoliticaBloqueo;

final class PoliticaBloqueoTest extends TestCase
{
    public function testCincoFallosActivanElBloqueo(): void
    {
        self::assertFalse(PoliticaBloqueo::debeBloquear(4));
        self::assertTrue(PoliticaBloqueo::debeBloquear(5));
        self::assertTrue(PoliticaBloqueo::debeBloquear(6));
    }

    public function testVentanaDeQuinceMinutos(): void
    {
        $ahora = new DateTimeImmutable('2026-09-09 10:00:00');
        $hasta = PoliticaBloqueo::bloqueadoHasta($ahora);
        self::assertSame('2026-09-09 10:15:00', $hasta->format('Y-m-d H:i:s'));
        self::assertTrue(PoliticaBloqueo::estaBloqueada($hasta, $ahora));
        self::assertFalse(PoliticaBloqueo::estaBloqueada($hasta, $ahora->modify('+16 minutes')));
        self::assertFalse(PoliticaBloqueo::estaBloqueada(null, $ahora));
    }
}
