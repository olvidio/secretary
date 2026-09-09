<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\shared\domain\value_objects\Dinero;
use src\shared\domain\value_objects\PeriodoEjercicio;

final class PeriodoEjercicioTest extends TestCase
{
    public function testMesesEneroADiciembreConCorteEnJunio(): void
    {
        // Caso real (Montagut 2026): ejercicio completo Enero-Diciembre, corte en junio.
        // fechaFin es el fin REAL del ejercicio (31/12), no la fecha de corte (30/06):
        // ver la nota de clase de PeriodoEjercicio sobre la trampa fechaFin/fechaCorte.
        $p = new PeriodoEjercicio(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-12-31'),
            new DateTimeImmutable('2026-06-30'),
        );
        self::assertSame(12, $p->mesesTotales());
        self::assertSame(6, $p->mesesTranscurridos());
        // contiene() valida contra el ejercicio completo, no contra el corte: una
        // fecha de diciembre es válida aunque el corte esté en junio.
        self::assertTrue($p->contiene(new DateTimeImmutable('2026-03-15')));
        self::assertTrue($p->contiene(new DateTimeImmutable('2026-12-15')));
        self::assertFalse($p->contiene(new DateTimeImmutable('2025-12-31')));
        self::assertFalse($p->contiene(new DateTimeImmutable('2027-01-01')));
    }

    public function testEjercicioLibreJunioAMayo(): void
    {
        // Ejercicio "curso" de período libre: Junio 2026 a Mayo 2027, con corte a
        // mitad de camino (finales de noviembre).
        $p = new PeriodoEjercicio(
            new DateTimeImmutable('2026-06-01'),
            new DateTimeImmutable('2027-05-31'),
            new DateTimeImmutable('2026-11-30'),
        );
        self::assertSame(12, $p->mesesTotales());
        self::assertSame(6, $p->mesesTranscurridos());
        self::assertTrue($p->contiene(new DateTimeImmutable('2027-03-01')));
        self::assertFalse($p->contiene(new DateTimeImmutable('2026-05-31')));
        self::assertFalse($p->contiene(new DateTimeImmutable('2027-06-01')));
    }

    public function testDineroSuma(): void
    {
        $a = Dinero::fromInput('10,50');
        $b = new Dinero('2.50');
        self::assertSame('13.00', $a->add($b)->toString());
        self::assertSame('5.25', (new Dinero('10.50'))->divInt(2)->toString());
    }
}
