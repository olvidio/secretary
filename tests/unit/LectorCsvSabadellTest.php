<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\personal\domain\services\CatalogoBancosCsv;
use src\personal\domain\services\LectorCsvSabadell;

final class LectorCsvSabadellTest extends TestCase
{
    public function testLeeExtractoConFechaOperYMiles(): void
    {
        $csv = "FECHA OPER;FECHA VALOR;CONCEPTO;IMPORTE;DIVISA;SALDO\n"
            . "28/06/2019;26/06/2019;PAGO FRA. SUMINISTRO;-87,29;Euro;353.820,51\n"
            . "28/06/2019;26/06/2019;REMESA RECIBOS;2.833,00;Euro;356.828,09\n"
            . "28/06/2019;26/06/2019;COMISION;0,00;Euro;356.828,09\n";
        $lineas = (new LectorCsvSabadell())->leer($csv);
        self::assertCount(2, $lineas);
        self::assertSame('2019-06-28', $lineas[0]->fecha);
        self::assertSame(-8729, $lineas[0]->cents);
        self::assertSame('gasto', $lineas[0]->sentido());
        self::assertSame('PAGO FRA. SUMINISTRO', $lineas[0]->concepto);
        self::assertSame(283300, $lineas[1]->cents);
        self::assertSame('ingreso', $lineas[1]->sentido());
        self::assertNotSame($lineas[0]->huella, $lineas[1]->huella);
    }

    public function testLeeVarianteCorta(): void
    {
        $csv = "Fecha;Concepto;Importe;Saldo\n"
            . "01/02/2026;Bizum Ana;-15,00;100,00\n";
        $lineas = (new LectorCsvSabadell())->leer($csv);
        self::assertCount(1, $lineas);
        self::assertSame('2026-02-01', $lineas[0]->fecha);
        self::assertSame(-1500, $lineas[0]->cents);
        self::assertSame('Bizum Ana', $lineas[0]->concepto);
    }

    public function testLeeFilasYaTabuladas(): void
    {
        $filas = [
            ['Cuenta', 'ES00'],
            ['Fecha operación', 'Fecha valor', 'Concepto', 'Importe EUR', 'Saldo'],
            ['2026-03-02', '2026-03-02', 'Nomina', '1500.50', '2000.50'],
        ];
        $lineas = (new LectorCsvSabadell())->leerFilas($filas);
        self::assertCount(1, $lineas);
        self::assertSame('2026-03-02', $lineas[0]->fecha);
        self::assertSame(150050, $lineas[0]->cents);
    }

    public function testRechazaExtractoBbva(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LectorCsvSabadell())->leer(
            "Fecha;F.Valor;Concepto;Movimiento;Importe;Disponible\n"
            . "01/05/2026;01/05/2026;Cafe;Pago con tarjeta;-3,00;10,00\n"
        );
    }

    public function testRechazaCabeceraN26(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LectorCsvSabadell())->leer("Date,Payee,Amount (EUR)\n2026-03-15,Cafe,-3.00\n");
    }

    public function testCatalogoIncluyeSabadell(): void
    {
        self::assertInstanceOf(LectorCsvSabadell::class, CatalogoBancosCsv::lector('sabadell'));
    }
}
