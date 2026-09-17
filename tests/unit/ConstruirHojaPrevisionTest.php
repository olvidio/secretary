<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\presupuestos\application\ConstruirHojaPrevision;

final class ConstruirHojaPrevisionTest extends TestCase
{
    public function testHastaTrasMesesCortaAlUltimoDiaDelMes(): void
    {
        $hasta = ConstruirHojaPrevision::hastaTrasMeses(
            new DateTimeImmutable('2025-01-01'),
            6,
            new DateTimeImmutable('2025-12-31'),
        );
        self::assertSame('2025-06-30', $hasta->format('Y-m-d'));
    }

    public function testHastaTrasMesesCeroQuedaAntesDelInicio(): void
    {
        $hasta = ConstruirHojaPrevision::hastaTrasMeses(
            new DateTimeImmutable('2025-01-01'),
            0,
            new DateTimeImmutable('2025-12-31'),
        );
        self::assertSame('2024-12-31', $hasta->format('Y-m-d'));
    }

    public function testHastaTrasMesesNoPasaDelTope(): void
    {
        $hasta = ConstruirHojaPrevision::hastaTrasMeses(
            new DateTimeImmutable('2025-09-01'),
            12,
            new DateTimeImmutable('2026-08-31'),
        );
        self::assertSame('2026-08-31', $hasta->format('Y-m-d'));
    }
}
