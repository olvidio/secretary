<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\personal\domain\services\PeriodoPersonal;

final class PeriodoPersonalTest extends TestCase
{
    public function testSinConfigUsaUltimoDia(): void
    {
        $p = PeriodoPersonal::periodo(2026, 9, null, false, null);
        self::assertSame('2026-09-01', $p['desde']->format('Y-m-d'));
        self::assertSame('2026-09-30', $p['hasta']->format('Y-m-d'));
    }

    public function testDia25(): void
    {
        $p = PeriodoPersonal::periodo(2026, 9, 25, false, null);
        self::assertSame('2026-09-25', $p['fecha_cierre']->format('Y-m-d'));
    }

    public function testDia25SabadoPasaALunes(): void
    {
        // Noviembre 2025: día 25 es martes; usamos abril 2026 donde el 25 es sábado
        $p = PeriodoPersonal::periodo(2026, 4, 25, true, null);
        self::assertSame('2026-04-27', $p['fecha_cierre']->format('Y-m-d'));
    }

    public function testOverrideMes(): void
    {
        $fecha = new DateTimeImmutable('2026-09-22');
        $p = PeriodoPersonal::periodo(2026, 9, 25, true, $fecha);
        self::assertSame('2026-09-22', $p['fecha_cierre']->format('Y-m-d'));
    }

    public function testRechazaOverrideFueraDeMes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PeriodoPersonal::periodo(2026, 9, null, false, new DateTimeImmutable('2026-10-01'));
    }

    public function testSiguienteDiaHabilDomingo(): void
    {
        $lun = PeriodoPersonal::siguienteDiaHabil(new DateTimeImmutable('2026-01-25'));
        self::assertSame('2026-01-26', $lun->format('Y-m-d'));
    }
}
