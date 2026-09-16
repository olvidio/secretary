<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\ambito\domain\entity\Cuenta;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\disponible\domain\services\ConstructorAsientoAsignacion;
use src\disponible\domain\services\ConstructorAsientoLiquidacionCc;
use src\personas\domain\entity\Persona;

final class ProyectorAsignacionTest extends TestCase
{
    public function testAsignacionProyectaGastos7EIngreso111(): void
    {
        $asiento = ConstructorAsientoAsignacion::construir(
            1,
            9,
            new DateTimeImmutable('2026-09-16'),
            11,
            [
                ['cuenta_id' => 71, 'importe_cents' => 20000],
                ['cuenta_id' => 74, 'importe_cents' => 20741],
            ],
            'Asignación labores JMG 2026-09-16',
        );
        self::assertNotNull($asiento);
        $asiento = new Asiento(
            50,
            $asiento->ejercicioId,
            $asiento->libro,
            $asiento->numero,
            $asiento->fecha,
            $asiento->glosa,
            $asiento->tipo,
            $asiento->origen,
            $asiento->personaId,
            $asiento->movimientos,
            $asiento->conceptoCodigo,
            $asiento->asientoParId,
            $asiento->fechaOperacion,
            $asiento->remesaId,
        );
        $cuentas = [
            71 => $this->cuenta(71, '71', 'gasto'),
            74 => $this->cuenta(74, '74', 'gasto'),
            11 => $this->cuenta(11, '111', 'ingreso'),
        ];
        $personas = [
            9 => new Persona(9, 'Juan', 'García', 'jmg', null, null, null, null, null, 1, 1),
        ];
        $filas = (new ProyectorAsientoAFilaExcel())->proyectarFilas($asiento, $cuentas, $personas);
        self::assertCount(3, $filas);
        $porCodigo = [];
        foreach ($filas as $f) {
            $porCodigo[$f->conceptoCodigo] = $f->cantidad->toString();
        }
        self::assertSame('200.00', $porCodigo['71']);
        self::assertSame('207.41', $porCodigo['74']);
        self::assertSame('407.41', $porCodigo['111']);
    }

    public function testLiquidacionCcNoGeneraApuntes(): void
    {
        $asiento = ConstructorAsientoLiquidacionCc::construir(
            1,
            9,
            new DateTimeImmutable('2026-09-16'),
            11,
            90,
            40741,
            'Liquidación saldo CC JMG 2026-09-16',
        );
        self::assertNotNull($asiento);
        $asiento = new Asiento(
            51,
            $asiento->ejercicioId,
            $asiento->libro,
            $asiento->numero,
            $asiento->fecha,
            $asiento->glosa,
            $asiento->tipo,
            $asiento->origen,
            $asiento->personaId,
            $asiento->movimientos,
        );
        $filas = (new ProyectorAsientoAFilaExcel())->proyectarFilas(
            $asiento,
            [
                11 => $this->cuenta(11, '111', 'ingreso'),
                90 => $this->cuenta(90, 'CC.JMG', 'personal'),
            ],
            [],
        );
        self::assertSame([], $filas);
    }

    private function cuenta(int $id, string $codigo, string $tipo): Cuenta
    {
        return new Cuenta($id, 1, null, null, null, 'P', $codigo, $codigo, '', $tipo, 'mixta', $codigo, true);
    }
}
