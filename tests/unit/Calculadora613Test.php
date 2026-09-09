<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\informes\domain\services\Calculadora613;
use src\presupuestos\domain\entity\LineaPresupuesto;
use src\shared\domain\value_objects\Dinero;
use src\shared\domain\value_objects\PeriodoEjercicio;

final class Calculadora613Test extends TestCase
{
    public function testProrrateoYSuma(): void
    {
        $periodo = new PeriodoEjercicio(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-12-31'),
            new DateTimeImmutable('2026-06-30'),
        );
        $presu = [new LineaPresupuesto('P', '111', new Dinero('1200.00'))];
        $realizado = ['111' => 10000];
        $lineas = Calculadora613::lineas($realizado, $presu, $periodo, [
            ['codigo' => '111', 'etiqueta' => 'Trabajo', 'codigos' => ['111']],
        ], 0);
        self::assertSame('600.00', $lineas[0]['previsto']);
        self::assertSame('100.00', $lineas[0]['realizado']);
    }
}
