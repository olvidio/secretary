<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\ambito\domain\entity\Ejercicio;
use src\presupuestos\domain\services\SiguientePeriodoEjercicio;

final class SiguientePeriodoEjercicioTest extends TestCase
{
    public function testAnioNaturalPasaAlSiguiente(): void
    {
        $actual = self::ejercicio('2026', '2026-01-01', '2026-12-31', '2026-09-30');
        $sig = SiguientePeriodoEjercicio::calcular($actual);

        self::assertSame('2027', $sig['etiqueta']);
        self::assertSame('2027-01-01', $sig['fecha_inicio']->format('Y-m-d'));
        self::assertSame('2027-12-31', $sig['fecha_fin']->format('Y-m-d'));
    }

    public function testCursoSeptiembreAgosto(): void
    {
        $actual = self::ejercicio('2025-26', '2025-09-01', '2026-08-31', '2026-03-31');
        $sig = SiguientePeriodoEjercicio::calcular($actual);

        self::assertSame('2026-27', $sig['etiqueta']);
        self::assertSame('2026-09-01', $sig['fecha_inicio']->format('Y-m-d'));
        self::assertSame('2027-08-31', $sig['fecha_fin']->format('Y-m-d'));
    }

    private static function ejercicio(
        string $etiqueta,
        string $inicio,
        string $fin,
        string $corte,
    ): Ejercicio {
        return new Ejercicio(
            1,
            1,
            $etiqueta,
            new DateTimeImmutable($inicio),
            new DateTimeImmutable($fin),
            new DateTimeImmutable($corte),
        );
    }
}
