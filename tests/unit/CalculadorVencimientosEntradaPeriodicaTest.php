<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\apuntes\domain\entity\EntradaPeriodica;
use src\apuntes\domain\services\CalculadorVencimientosEntradaPeriodica;
use src\apuntes\domain\value_objects\PeriodicidadEntrada;
use src\shared\domain\value_objects\Dinero;

final class CalculadorVencimientosEntradaPeriodicaTest extends TestCase
{
    private CalculadorVencimientosEntradaPeriodica $calc;

    protected function setUp(): void
    {
        $this->calc = new CalculadorVencimientosEntradaPeriodica();
    }

    public function testMensualListaOcurrenciasHastaHoy(): void
    {
        $def = $this->def('mensual', '2026-01-15');
        $hasta = new DateTimeImmutable('2026-03-20');
        $pend = $this->calc->pendientes($def, $hasta, []);
        self::assertSame(['2026-01-15', '2026-02-15', '2026-03-15'], $this->fechas($pend));
    }

    public function testExcluyeEjecutadas(): void
    {
        $def = $this->def('mensual', '2026-01-15');
        $hasta = new DateTimeImmutable('2026-03-20');
        $pend = $this->calc->pendientes($def, $hasta, ['2026-02-15']);
        self::assertSame(['2026-01-15', '2026-03-15'], $this->fechas($pend));
    }

    public function testTrimestral(): void
    {
        $def = $this->def('trimestral', '2026-01-10');
        $hasta = new DateTimeImmutable('2026-10-01');
        $pend = $this->calc->pendientes($def, $hasta, []);
        self::assertSame(['2026-01-10', '2026-04-10', '2026-07-10'], $this->fechas($pend));
    }

    private function def(string $periodicidad, string $ancla): EntradaPeriodica
    {
        return new EntradaPeriodica(
            1,
            1,
            'AB',
            '21',
            null,
            new Dinero('10.00'),
            PeriodicidadEntrada::fromString($periodicidad),
            new DateTimeImmutable($ancla),
            true,
        );
    }

    /** @param list<DateTimeImmutable> $fechas */
    private function fechas(array $fechas): array
    {
        return array_map(static fn (DateTimeImmutable $d): string => $d->format('Y-m-d'), $fechas);
    }
}
