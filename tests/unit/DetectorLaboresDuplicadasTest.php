<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\disponible\domain\services\DetectorLaboresDuplicadas;

final class DetectorLaboresDuplicadasTest extends TestCase
{
    /** @return array{asiento_id:int, numero:int, fecha:string, tipo:string, origen:string, persona_id:?int, iniciales:string, codigo:string, cents:int} */
    private static function fila(int $id, string $origen, string $tipo, int $persona, string $codigo, int $cents, string $fecha = '2026-10-09'): array
    {
        return [
            'asiento_id' => $id, 'numero' => $id, 'fecha' => $fecha, 'tipo' => $tipo, 'origen' => $origen,
            'persona_id' => $persona, 'iniciales' => 'p' . $persona, 'codigo' => $codigo, 'cents' => $cents,
        ];
    }

    /** El caso de JMG: asignación del 9/10 y remesa de octubre con la misma transferencia a Proas. */
    public function testAsignacionYRemesaConMismaPartidaEImporteSonDuplicado(): void
    {
        $pares = DetectorLaboresDuplicadas::detectar([
            self::fila(1, 'asignacion', 'normal', 21, '73', 232251),
            self::fila(2, 'remesa', 'remesa', 21, '73', 232251, '2026-10-31'),
        ]);

        self::assertCount(1, $pares);
        self::assertSame(1, $pares[0]['asignacion']['asiento_id']);
        self::assertSame(2, $pares[0]['otro']['asiento_id']);
        self::assertSame(232251, $pares[0]['cents']);
    }

    /** El caso de RD: asignación sin ningún otro apunte igual no es duplicado. */
    public function testAsignacionSolaNoEsDuplicado(): void
    {
        self::assertSame([], DetectorLaboresDuplicadas::detectar([
            self::fila(1, 'asignacion', 'normal', 19, '73', 109442),
            self::fila(2, 'import', 'normal', 19, '73', 400000),
        ]));
    }

    public function testOtraPersonaOtraPartidaOOtroImporteNoCuentan(): void
    {
        self::assertSame([], DetectorLaboresDuplicadas::detectar([
            self::fila(1, 'asignacion', 'normal', 21, '73', 1000),
            self::fila(2, 'remesa', 'remesa', 19, '73', 1000),
            self::fila(3, 'remesa', 'remesa', 21, '71', 1000),
            self::fila(4, 'remesa', 'remesa', 21, '73', 999),
        ]));
    }

    public function testDosAsignacionesNoSeEmparejanEntreSi(): void
    {
        self::assertSame([], DetectorLaboresDuplicadas::detectar([
            self::fila(1, 'asignacion', 'normal', 21, '73', 1000),
            self::fila(2, 'asignacion', 'normal', 21, '73', 1000),
        ]));
    }

    public function testCadaApunteSeEmparejaUnaSolaVez(): void
    {
        $pares = DetectorLaboresDuplicadas::detectar([
            self::fila(1, 'asignacion', 'normal', 21, '73', 1000),
            self::fila(2, 'asignacion', 'normal', 21, '73', 1000),
            self::fila(3, 'remesa', 'remesa', 21, '73', 1000),
        ]);

        self::assertCount(1, $pares);
    }

    public function testTotalAsientoSumaTodasSusPartidas7(): void
    {
        $filas = [
            self::fila(1, 'asignacion', 'normal', 21, '73', 1000),
            self::fila(1, 'asignacion', 'normal', 21, '71', 500),
            self::fila(2, 'remesa', 'remesa', 21, '73', 1000),
        ];

        self::assertSame(1500, DetectorLaboresDuplicadas::totalAsiento($filas, 1));
    }
}
