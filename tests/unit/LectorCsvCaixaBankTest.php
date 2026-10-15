<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\importacion\domain\services\CatalogoBancosCsv;
use src\importacion\domain\services\IdentificarBancoExtracto;
use src\importacion\domain\services\LectorCsvCaixaBank;
use src\importacion\infrastructure\excel\ExtractoCaixaBankDesdeArchivo;

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

    public function testLeeMovimentsDelCompteAmbCapcaleraCatalana(): void
    {
        $filas = [
            ['Moviments del compte ES70 2100 0007 8802 0015 0178 (CCC: 2100 0007 88 02 00150178)'],
            ['Imports expressats en euros'],
            ['Data', 'Data valor', 'Moviment', 'Més dades', 'Import', 'Saldo'],
            ['2026-10-09', '2026-10-09', 'Transferencia fet', 'Fundacio Proas', '-2322.51', '1104.72'],
            ['2026-10-08', '2026-10-08', 'PARLEM TELECOM', 'Rebuts varis', '-21.38', '3427.23'],
        ];
        $lineas = (new LectorCsvCaixaBank())->leerFilas($filas);
        self::assertCount(2, $lineas);
        self::assertSame('2026-10-09', $lineas[0]->fecha);
        self::assertSame(-232251, $lineas[0]->cents);
        self::assertSame('Transferencia fet · Fundacio Proas', $lineas[0]->concepto);
    }

    public function testLeeMovimentsDelCompteSenseFilaCapcalera(): void
    {
        $filas = [
            ['Moviments del compte ES12 2100 0000 0000 0000 0001'],
            ['Imports expressats en euros'],
            ['2026-10-01', '2026-10-01', 'REINT.CAIXER', '', '-100', '500.00'],
        ];
        $lineas = (new LectorCsvCaixaBank())->leerFilas($filas);
        self::assertCount(1, $lineas);
        self::assertSame(-10000, $lineas[0]->cents);
    }

    public function testDetectaXlsMovimentsCompteReal(): void
    {
        $path = '/home/dani/Escriptori/estractesBancsTest/Moviments_compte_0150178(9).xls';
        if (!is_readable($path)) {
            self::markTestSkipped('Fichero CaixaBank xls no disponible');
        }
        $filas = ExtractoCaixaBankDesdeArchivo::filas($path);
        self::assertSame('caixabank', (new IdentificarBancoExtracto())->desdeFilas($filas));
        $lineas = (new LectorCsvCaixaBank())->leerFilas($filas);
        self::assertCount(20, $lineas);
    }
}
