<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\informes\domain\services\SaldoCuentasCorrientes;

final class SaldoCuentasCorrientesTest extends TestCase
{
    public function testSumaLaUltimaAceptadaDeCadaPersonaHastaElCierre(): void
    {
        $cents = SaldoCuentasCorrientes::cents([
            ['persona_id' => 1, 'anio' => 2026, 'mes' => 8, 'version' => 1, 'saldo_cents' => 10000],
            ['persona_id' => 1, 'anio' => 2026, 'mes' => 9, 'version' => 1, 'saldo_cents' => 232251],
            ['persona_id' => 1, 'anio' => 2026, 'mes' => 9, 'version' => 2, 'saldo_cents' => 300000],
            ['persona_id' => 2, 'anio' => 2026, 'mes' => 9, 'version' => 2, 'saldo_cents' => 100000],
            ['persona_id' => 3, 'anio' => 2026, 'mes' => 10, 'version' => 1, 'saldo_cents' => 50000],
            ['persona_id' => 4, 'anio' => 2026, 'mes' => 9, 'version' => 1, 'saldo_cents' => null],
        ], 2026, 9);

        self::assertSame(400000, $cents);
    }

    public function testSinRemesasEsCero(): void
    {
        self::assertSame(0, SaldoCuentasCorrientes::cents([], 2026, 9));
    }
}
