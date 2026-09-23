<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\personal\domain\services\CatalogoBancosCsv;
use src\personal\domain\services\LectorCsvN26;

final class LectorCsvN26Test extends TestCase
{
    public function testLeeExtractoIngles(): void
    {
        $csv = <<<CSV
"Date","Payee","Account number","Transaction type","Payment reference","Category","Amount (EUR)","Amount (Foreign Currency)","Type Foreign Currency","Exchange Rate"
"2026-03-15","Mercadona","DE00","Card","Compra","Food","-12.50","","",""
"2026-03-16","Empresa","DE00","Income","Nomina","Income","1000.00","","",""
"2026-03-17","Cero","DE00","Card","","","0.00","","",""
CSV;
        $lineas = (new LectorCsvN26())->leer($csv);
        self::assertCount(2, $lineas);
        self::assertSame('2026-03-15', $lineas[0]->fecha);
        self::assertSame(-1250, $lineas[0]->cents);
        self::assertSame('gasto', $lineas[0]->sentido());
        self::assertStringContainsString('Mercadona', $lineas[0]->concepto);
        self::assertSame(100000, $lineas[1]->cents);
        self::assertSame('ingreso', $lineas[1]->sentido());
        self::assertNotSame($lineas[0]->huella, $lineas[1]->huella);
    }

    public function testLeeExtractoEspanol(): void
    {
        $csv = <<<CSV
"Fecha","Beneficiario","Número de cuenta","Tipo de transacción","Referencia de pago","Categoría","Importe (EUR)"
"15/03/2026","Café","ES00","Pago con tarjeta","Desayuno","Comida","-3,40"
CSV;
        $lineas = (new LectorCsvN26())->leer($csv);
        self::assertCount(1, $lineas);
        self::assertSame('2026-03-15', $lineas[0]->fecha);
        self::assertSame(-340, $lineas[0]->cents);
    }

    public function testLeeExtractoAleman(): void
    {
        $csv = <<<CSV
"Datum","Empfänger","Kontonummer","Transaktionstyp","Verwendungszweck","Kategorie","Betrag (EUR)","Betrag (Fremdwährung)","Fremdwährung","Wechselkurs"
"2026-02-08","Yabox","DE00","Lastschrift","Nota","Medien","-1.62","","",""
CSV;
        $lineas = (new LectorCsvN26())->leer($csv);
        self::assertCount(1, $lineas);
        self::assertSame(-162, $lineas[0]->cents);
        self::assertSame('Yabox · Nota', $lineas[0]->concepto);
    }

    public function testRechazaCabeceraAjena(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LectorCsvN26())->leer("foo,bar\n1,2\n");
    }

    public function testCatalogoRechazaBancoDesconocido(): void
    {
        self::assertSame(['n26', 'caixabank', 'bbva', 'sabadell'], array_column(CatalogoBancosCsv::todos(), 'id'));
        $this->expectException(InvalidArgumentException::class);
        CatalogoBancosCsv::lector('ing');
    }

    public function testMismaFilaMismaHuella(): void
    {
        $csv = "Date,Payee,Account number,Transaction type,Payment reference,Amount (EUR)\n"
            . "2026-03-15,Cafe,DE00,Card,x,-3.00\n";
        $a = (new LectorCsvN26())->leer($csv);
        $b = (new LectorCsvN26())->leer($csv);
        self::assertSame($a[0]->huella, $b[0]->huella);
    }

    public function testLeeExtractoBookingDateYPartnerName(): void
    {
        $csv = <<<CSV
"Booking Date","Value Date","Partner Name","Partner Iban",Type,"Payment Reference","Account Name","Amount (EUR)","Original Amount","Original Currency","Exchange Rate"
2026-07-02,2026-07-02,"AREA TRUCK",,Presentment,,"Cuenta Principal",-53.51,53.51,EUR,1
2026-07-02,2026-07-02,,,"Credit Transfer","Cap de setmana","Cuenta Principal",25.000000000,,,
2026-07-04,2026-07-03,"EL CORTE INGLES",,Presentment,,"Cuenta Principal",-179.8,179.8,EUR,1
2026-08-11,2026-08-11,,,"Debit Transfer",,"Cuenta Principal",-500.000000000,,,
CSV;
        $lineas = (new LectorCsvN26())->leer($csv);
        self::assertCount(4, $lineas);
        self::assertSame('2026-07-02', $lineas[0]->fecha);
        self::assertSame(-5351, $lineas[0]->cents);
        self::assertSame('AREA TRUCK', $lineas[0]->concepto);
        self::assertSame(2500, $lineas[1]->cents);
        self::assertSame('ingreso', $lineas[1]->sentido());
        self::assertSame('Cap de setmana', $lineas[1]->concepto);
        self::assertSame(-17980, $lineas[2]->cents);
        self::assertSame(-50000, $lineas[3]->cents);
        self::assertSame('Debit Transfer', $lineas[3]->concepto);
    }
}
