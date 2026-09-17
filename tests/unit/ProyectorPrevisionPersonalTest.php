<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\presupuestos\domain\services\ProyectorPrevisionPersonal;

final class ProyectorPrevisionPersonalTest extends TestCase
{
    public function testLinealDuplicaSiVaLaMitadDelEjercicio(): void
    {
        self::assertSame(
            120000,
            ProyectorPrevisionPersonal::proyectar('111', 60000, 0, 0, 6, 12),
        );
    }

    public function testLinealSinMesesUsaElEjercicioAnterior(): void
    {
        self::assertSame(
            90000,
            ProyectorPrevisionPersonal::proyectar('22', 0, 90000, 0, 0, 12),
        );
    }

    public function testPuntualSumaElRestoDelAnioAnterior(): void
    {
        // 24-ca en julio: a junio este año 0; el año pasado 0 hasta junio y 500 € en julio.
        self::assertSame(
            50000,
            ProyectorPrevisionPersonal::proyectar('24', 0, 50000, 0, 6, 12),
        );
    }

    public function testPuntualYaAnotadoNoAnadeLoQueYaPasoElAnioAnterior(): void
    {
        self::assertSame(
            48000,
            ProyectorPrevisionPersonal::proyectar('24', 48000, 50000, 50000, 8, 12),
        );
    }

    public function testPuntualRetrasadoUsaElTotalAnterior(): void
    {
        // En agosto el CA del año pasado ya había pasado, este año aún no.
        self::assertSame(
            50000,
            ProyectorPrevisionPersonal::proyectar('24', 0, 50000, 50000, 8, 12),
        );
    }

    public function testSaldoNoExtrapola(): void
    {
        self::assertSame(
            1234,
            ProyectorPrevisionPersonal::proyectar('9', 1234, 9999, 111, 6, 12),
        );
    }

    public function testModo(): void
    {
        self::assertSame('lineal', ProyectorPrevisionPersonal::modo('111'));
        self::assertSame('puntual', ProyectorPrevisionPersonal::modo('24'));
        self::assertSame('saldo', ProyectorPrevisionPersonal::modo('9'));
    }
}
