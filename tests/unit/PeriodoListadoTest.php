<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\listados\domain\services\PeriodoListado;

final class PeriodoListadoTest extends TestCase
{
    public function testMesActual(): void
    {
        self::assertSame(
            ['desde' => '2026-11-01', 'hasta' => '2026-11-30'],
            PeriodoListado::fechasDelMes('mes_actual', new DateTimeImmutable('2026-11-13'))
        );
    }

    public function testMesAnteriorDesdeUnDia31(): void
    {
        // El 31 de marzo, «-1 month» sin ir al día 1 caería en marzo otra vez.
        self::assertSame(
            ['desde' => '2024-02-01', 'hasta' => '2024-02-29'],
            PeriodoListado::fechasDelMes('mes_anterior', new DateTimeImmutable('2024-03-31'))
        );
    }

    public function testMesAnteriorCruzaElAno(): void
    {
        self::assertSame(
            ['desde' => '2025-12-01', 'hasta' => '2025-12-31'],
            PeriodoListado::fechasDelMes('mes_anterior', new DateTimeImmutable('2026-01-05'))
        );
    }

    public function testSoloLosDeMesSonMes(): void
    {
        self::assertTrue(PeriodoListado::esMes('mes_anterior'));
        self::assertFalse(PeriodoListado::esMes('anterior'));
    }
}
