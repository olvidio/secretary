<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\personal\domain\services\CatalogoBancosCsv;
use src\personal\domain\services\LectorCsvBbva;

final class LectorCsvBbvaTest extends TestCase
{
    public function testLeeExtractoConPuntoDecimalYCabeceraDesplazada(): void
    {
        $csv = <<<CSV
;Fecha;F.Valor;Concepto;Movimiento;Importe;Divisa;Disponible;Divisa;Observaciones
;31/01/2023;29/01/2023;ventas.com;Pago con tarjeta;-16.41;EUR;24800.46;EUR;compra web
;31/01/2023;31/01/2023;Cargo por amortizacion de prestamo/credito; ;-700.83;EUR;4816.87;EUR;
;30/01/2023;30/01/2023;Abono de nomina;"Mi Empresa; S.L";2380.43;EUR;25153.7;EUR;"Mi Empresa; S.L"
CSV;
        $lineas = (new LectorCsvBbva())->leer($csv);
        self::assertCount(3, $lineas);
        self::assertSame('2023-01-31', $lineas[0]->fecha);
        self::assertSame(-1641, $lineas[0]->cents);
        self::assertSame('gasto', $lineas[0]->sentido());
        self::assertSame('ventas.com · Pago con tarjeta · compra web', $lineas[0]->concepto);
        self::assertSame(-70083, $lineas[1]->cents);
        self::assertSame('Cargo por amortizacion de prestamo/credito', $lineas[1]->concepto);
        self::assertSame(238043, $lineas[2]->cents);
        self::assertSame('ingreso', $lineas[2]->sentido());
        self::assertSame('Abono de nomina · Mi Empresa; S.L', $lineas[2]->concepto);
        self::assertNotSame($lineas[0]->huella, $lineas[1]->huella);
    }

    public function testLeeMilesConComaYOmiteCeros(): void
    {
        $csv = "Cuenta;ES00\n"
            . "Fecha;F.Valor;Concepto;Movimiento;Importe;Divisa;Disponible;Observaciones\n"
            . "12/03/2026;12/03/2026;MERCADONA;Pago con tarjeta;-1.234,56;EUR;5.000,00;\n"
            . "13/03/2026;13/03/2026;NOMINA;Abono;1.200,00;EUR;6.200,00;\n"
            . "14/03/2026;14/03/2026;COMISION;;0,00;EUR;6.200,00;\n";
        $lineas = (new LectorCsvBbva())->leer($csv);
        self::assertCount(2, $lineas);
        self::assertSame('2026-03-12', $lineas[0]->fecha);
        self::assertSame(-123456, $lineas[0]->cents);
        self::assertSame(120000, $lineas[1]->cents);
    }

    public function testLeeFilasYaTabuladas(): void
    {
        $filas = [
            ['Titular', 'Ana'],
            ['Fecha', 'F.Valor', 'Concepto', 'Movimiento', 'Importe', 'Disponible'],
            ['2026-04-01', '2026-04-01', 'BIZUM', 'Transferencia', '-20.00', '80.00'],
        ];
        $lineas = (new LectorCsvBbva())->leerFilas($filas);
        self::assertCount(1, $lineas);
        self::assertSame('2026-04-01', $lineas[0]->fecha);
        self::assertSame(-2000, $lineas[0]->cents);
        self::assertSame('BIZUM · Transferencia', $lineas[0]->concepto);
    }

    public function testRechazaCabeceraAjena(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LectorCsvBbva())->leer("Fecha;Fecha valor;Movimiento;Más datos;Importe;Saldo\n01/01/2026;01/01/2026;COMPRA;TIENDA;-3,40;10,00\n");
    }

    public function testMismaFilaMismaHuella(): void
    {
        $csv = "Fecha;F.Valor;Concepto;Movimiento;Importe;Disponible\n"
            . "01/05/2026;01/05/2026;Cafe;Pago con tarjeta;-3,00;10,00\n";
        $a = (new LectorCsvBbva())->leer($csv);
        $b = (new LectorCsvBbva())->leer($csv);
        self::assertSame($a[0]->huella, $b[0]->huella);
    }

    public function testCatalogoIncluyeBbva(): void
    {
        self::assertInstanceOf(LectorCsvBbva::class, CatalogoBancosCsv::lector('bbva'));
    }
}
