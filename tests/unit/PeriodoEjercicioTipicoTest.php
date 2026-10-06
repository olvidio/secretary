<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\ambito\domain\services\PeriodoEjercicioTipico;

final class PeriodoEjercicioTipicoTest extends TestCase
{
    public function testAnioNatural(): void
    {
        [$ini, $fin] = PeriodoEjercicioTipico::fechas(2026, PeriodoEjercicioTipico::MODO_ANO);
        self::assertSame('2026-01-01', $ini->format('Y-m-d'));
        self::assertSame('2026-12-31', $fin->format('Y-m-d'));
        self::assertSame(
            PeriodoEjercicioTipico::MODO_ANO,
            PeriodoEjercicioTipico::modoDesdeFechas($ini, $fin),
        );
    }

    public function testCurso(): void
    {
        [$ini, $fin] = PeriodoEjercicioTipico::fechas(2025, PeriodoEjercicioTipico::MODO_CURSO);
        self::assertSame('2025-09-01', $ini->format('Y-m-d'));
        self::assertSame('2026-08-31', $fin->format('Y-m-d'));
        self::assertSame(
            PeriodoEjercicioTipico::MODO_CURSO,
            PeriodoEjercicioTipico::modoDesdeFechas($ini, $fin),
        );
    }
}
