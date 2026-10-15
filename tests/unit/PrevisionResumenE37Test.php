<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\presupuestos\domain\services\PrevisionResumenE37;

final class PrevisionResumenE37Test extends TestCase
{
    public function testFilaPersonaUsaReglasE37(): void
    {
        $f = PrevisionResumenE37::filaPersona('Ana B', 'ab', [
            '111' => 100000,
            '211' => 40000,
            '4' => 5000,
            '9' => 0,
        ]);

        self::assertSame('Ana B', $f['nombre']);
        self::assertSame('1.000,00', $f['ingresos_es']);
        self::assertSame('400,00', $f['gastos_es']);
        self::assertSame('400,00', $f['vivienda_es']);
        self::assertSame('600,00', $f['disponible_es']);
        self::assertSame('550,00', $f['saldo_final_es']);
    }
}
