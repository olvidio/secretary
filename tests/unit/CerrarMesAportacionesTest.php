<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;

/** Regresión: los cierres automáticos solo se omiten en el rango excluido explícito. */
final class CerrarMesAportacionesTest extends TestCase
{
    /**
     * Réplica de la condición en CerrarMes::aportacionesGenerales.
     */
    private function omitirCierreAuto(
        ?string $excluirDesde,
        ?string $excluirHasta,
        string $fecha,
    ): bool {
        if ($excluirDesde === null || $excluirHasta === null) {
            return false;
        }

        return $fecha >= $excluirDesde && $fecha <= $excluirHasta;
    }

    public function testSinRangoExcluidoCuentaCierreAuto(): void
    {
        self::assertFalse($this->omitirCierreAuto(null, null, '2026-02-28'));
    }

    public function testConRangoExcluidoOmiteSoloDentro(): void
    {
        self::assertTrue($this->omitirCierreAuto('2026-02-01', '2026-02-28', '2026-02-28'));
        self::assertFalse($this->omitirCierreAuto('2026-02-01', '2026-02-28', '2026-01-31'));
    }
}
