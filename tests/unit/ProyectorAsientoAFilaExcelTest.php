<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\apuntes\domain\services\SaldoCuadreApuntes;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\personas\domain\entity\Persona;

final class ProyectorAsientoAFilaExcelTest extends TestCase
{
    public function testRemesaConVariosConceptosProyectaUnaFilaPorLinea(): void
    {
        $proyector = new ProyectorAsientoAFilaExcel();
        $cuentas = [
            22 => $this->cuenta(22, '22', 'gasto'),
            11 => $this->cuenta(11, '111', 'ingreso'),
            90 => $this->cuenta(90, 'CC.JMG', 'personal'),
        ];
        $personas = [
            9 => new Persona(9, 'Juan', 'García', 'jmg', null, null, null, null, null, 1, 1),
        ];
        $asiento = new Asiento(
            100,
            1,
            'P',
            null,
            new DateTimeImmutable('2026-01-31'),
            'Remesa JMG 01/2026 v1',
            'remesa',
            'remesa',
            9,
            [
                new Movimiento(null, 1, 22, 9, 1250, 0),
                new Movimiento(null, 2, 11, 9, 0, 5000),
                new Movimiento(null, 3, 90, 9, 3750, 0),
            ],
            null,
            null,
            null,
            44,
        );

        $filas = $proyector->proyectarFilas($asiento, $cuentas, $personas);

        self::assertCount(3, $filas);
        $porCodigo = [];
        foreach ($filas as $f) {
            $porCodigo[$f->conceptoCodigo] = $f->cantidad->toString();
        }
        self::assertSame('12.50', $porCodigo['22']);
        self::assertSame('12.50', $porCodigo['111']);
        self::assertSame('37.50', $porCodigo['9']);
        self::assertSame('jmg', $filas[0]->iniciales);
    }

    /** Remesa de octubre de JMG: gastos por encima de los ingresos, la diferencia la abona la c/c. */
    public function testRemesaConDeficitAnadeLinea9NegativaQueCuadraLosApuntesA(): void
    {
        $proyector = new ProyectorAsientoAFilaExcel();
        $cuentas = [
            21 => $this->cuenta(21, '212', 'gasto'),
            73 => $this->cuenta(73, '73', 'gasto'),
            11 => $this->cuenta(11, '111', 'ingreso'),
            90 => $this->cuenta(90, 'CC.JMG', 'personal'),
        ];
        $personas = [9 => new Persona(9, 'Juan', 'García', 'jmg', null, null, null, null, null, 1, 1)];
        $asiento = new Asiento(
            102, 1, 'P', null, new DateTimeImmutable('2026-10-31'), 'Remesa JMG 10/2026 v2', 'remesa', 'remesa', 9,
            [
                new Movimiento(null, 1, 11, 9, 0, 238900),
                new Movimiento(null, 2, 21, 9, 85129, 0),
                new Movimiento(null, 3, 73, 9, 232251, 0),
                new Movimiento(null, 4, 90, 9, 0, 78480),
            ],
            null, null, null, 38,
        );

        $filas = $proyector->proyectarFilas($asiento, $cuentas, $personas);

        $porCodigo = [];
        foreach ($filas as $f) {
            $porCodigo[$f->conceptoCodigo] = $f;
        }
        self::assertSame('2389.00', $porCodigo['111']->cantidad->toString());
        self::assertSame('2322.51', $porCodigo['73']->cantidad->toString());
        self::assertSame('-784.80', $porCodigo['9']->cantidad->toString());
        self::assertTrue($porCodigo['9']->contrapartidaCc);

        $naturalezas = ['212' => 'gasto', '73' => 'gasto', '111' => 'ingreso', '9' => 'personal'];
        $cuadre = 0;
        foreach ($filas as $f) {
            $cuadre += SaldoCuadreApuntes::signedFila($f->toArray(), $naturalezas);
        }
        self::assertSame(0, $cuadre);
    }

    public function testRemesaConSobranteNoMarcaLaLinea9ComoContrapartida(): void
    {
        $proyector = new ProyectorAsientoAFilaExcel();
        $cuentas = [
            22 => $this->cuenta(22, '22', 'gasto'),
            11 => $this->cuenta(11, '111', 'ingreso'),
            90 => $this->cuenta(90, 'CC.JMG', 'personal'),
        ];
        $personas = [9 => new Persona(9, 'Juan', 'García', 'jmg', null, null, null, null, null, 1, 1)];
        $asiento = new Asiento(
            103, 1, 'P', null, new DateTimeImmutable('2026-09-30'), 'Remesa JMG 09/2026 v1', 'remesa', 'remesa', 9,
            [
                new Movimiento(null, 1, 22, 9, 1250, 0),
                new Movimiento(null, 2, 11, 9, 0, 5000),
                new Movimiento(null, 3, 90, 9, 3750, 0),
            ],
            null, null, null, 30,
        );

        foreach ($proyector->proyectarFilas($asiento, $cuentas, $personas) as $f) {
            self::assertFalse($f->contrapartidaCc);
            self::assertArrayNotHasKey('contrapartida_cc', $f->toArray());
        }
    }

    public function testAparcamientoDeRemesaNoGeneraFilas(): void
    {
        $proyector = new ProyectorAsientoAFilaExcel();
        $cuentas = [
            90 => $this->cuenta(90, 'CC.JMG', 'personal'),
            91 => $this->cuenta(91, 'DISP.JMG', 'personal'),
        ];
        $asiento = new Asiento(
            101,
            1,
            'P',
            null,
            new DateTimeImmutable('2026-01-31'),
            'Aparcar sobrante JMG 01/2026 v1',
            'remesa',
            'remesa',
            9,
            [
                new Movimiento(null, 1, 91, 9, 3750, 0),
                new Movimiento(null, 2, 90, 9, 0, 3750),
            ],
            null,
            null,
            null,
            44,
        );

        self::assertSame([], $proyector->proyectarFilas($asiento, $cuentas, []));
    }

    private function cuenta(int $id, string $codigo, string $tipo): Cuenta
    {
        return new Cuenta($id, 1, null, null, null, 'P', $codigo, $codigo, '', $tipo, 'mixta', $codigo, true);
    }
}
