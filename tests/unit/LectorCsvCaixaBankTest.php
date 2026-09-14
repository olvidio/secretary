<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\personal\domain\services\CatalogoBancosCsv;
use src\personal\domain\services\LectorCsvCaixaBank;

final class LectorCsvCaixaBankTest extends TestCase
{
    public function testLeeExtractoCastellanoConMiles(): void
    {
        $csv = "Cuenta;ES00\n"
            . "Fecha;Fecha valor;Movimiento;Más datos;Importe;Saldo\n"
            . "12/03/2026;12/03/2026;COMPRA TARJETA;CAPRABO 7776;-16,04;1.218,52\n"
            . "13/03/2026;13/03/2026;TRANSFERENCIA RECIBIDA;NOMINA;1.200,00;2.418,52\n"
            . "14/03/2026;14/03/2026;COMISION;;0,00;2.418,52\n";
        $lineas = (new LectorCsvCaixaBank())->leer($csv);
        self::assertCount(2, $lineas);
        self::assertSame('2026-03-12', $lineas[0]->fecha);
        self::assertSame(-1604, $lineas[0]->cents);
        self::assertSame('gasto', $lineas[0]->sentido());
        self::assertSame('COMPRA TARJETA · CAPRABO 7776', $lineas[0]->concepto);
        self::assertSame(120000, $lineas[1]->cents);
        self::assertSame('ingreso', $lineas[1]->sentido());
        self::assertNotSame($lineas[0]->huella, $lineas[1]->huella);
    }

    public function testLeeExtractoCatalan(): void
    {
        $csv = "Data;Data valor;Moviment;Més dades;Import;Saldo\n"
            . "01/08/2026;01/08/2026;COMPRA;MERCADONA;-3,40;100,00\n";
        $lineas = (new LectorCsvCaixaBank())->leer($csv);
        self::assertCount(1, $lineas);
        self::assertSame('2026-08-01', $lineas[0]->fecha);
        self::assertSame(-340, $lineas[0]->cents);
        self::assertSame('COMPRA · MERCADONA', $lineas[0]->concepto);
    }

    public function testRechazaCabeceraAjena(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LectorCsvCaixaBank())->leer("Date,Payee,Amount (EUR)\n2026-03-15,Cafe,-3.00\n");
    }

    public function testCatalogoIncluyeCaixaBank(): void
    {
        self::assertInstanceOf(LectorCsvCaixaBank::class, CatalogoBancosCsv::lector('caixabank'));
    }
}
