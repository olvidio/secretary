<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\importacion\domain\services\IdentificarBancoExtracto;
use src\importacion\domain\services\LectorCsvPropio;

final class LectorCsvPropioTest extends TestCase
{
    public function testLeeLaPlantillaQueSeOfreceParaDescargar(): void
    {
        $csv = (string) file_get_contents(dirname(__DIR__, 2) . '/public/plantillas/extracto-propio.csv');

        self::assertSame(LectorCsvPropio::ID, (new IdentificarBancoExtracto())->desdeCsv($csv));
        $lineas = (new LectorCsvPropio())->leer($csv);

        self::assertCount(2, $lineas);
        self::assertSame('2026-01-15', $lineas[0]->fecha);
        self::assertSame(-2350, $lineas[0]->cents);
        self::assertSame('Compra de papelería', $lineas[0]->concepto);
        self::assertSame(10000, $lineas[1]->cents);
    }

    public function testAdmiteComasCabeceraEnCatalanYOtroOrden(): void
    {
        $csv = "Concepte,Data,Import\n\"Quota, febrer\",2026-02-03,-12.5\n\nIngrés,03/02/2026,\"1.250,00\"\n";

        $lineas = (new LectorCsvPropio())->leer($csv);

        self::assertCount(2, $lineas);
        self::assertSame('Quota, febrer', $lineas[0]->concepto);
        self::assertSame(-1250, $lineas[0]->cents);
        self::assertSame('2026-02-03', $lineas[1]->fecha);
        self::assertSame(125000, $lineas[1]->cents);
    }

    public function testDesdeExcelLaFechaPuedeLlegarComoNumeroDeSerie(): void
    {
        $lineas = (new LectorCsvPropio())->leerFilas([
            ['Fecha', 'Concepto', 'Importe'],
            ['46037', 'Luz', '-40'],
        ]);

        self::assertSame('2026-01-15', $lineas[0]->fecha);
    }

    public function testDosFilasDistintasTienenHuellaDistintaYLaMismaFilaLaMisma(): void
    {
        $lector = new LectorCsvPropio();
        $a = $lector->leer("Fecha;Concepto;Importe\n15/01/2026;Luz;-40\n15/01/2026;Agua;-40\n");
        $b = $lector->leer("Fecha;Concepto;Importe\n15/01/2026;Luz;-40,00\n");

        self::assertNotSame($a[0]->huella, $a[1]->huella);
        self::assertSame($a[0]->huella, $b[0]->huella);
    }

    public function testSinCabeceraNoSeReconoceYLaFilaMalaDiceCual(): void
    {
        $lector = new LectorCsvPropio();
        self::assertFalse($lector->reconoceFilas([['15/01/2026', 'Luz', '-40']]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Fila 3');
        $lector->leer("Fecha;Concepto;Importe\n15/01/2026;Luz;-40\n31/02/2026;Agua;-10\n");
    }

    public function testConColumnasDeMasYaNoEsElFormatoPropio(): void
    {
        self::assertFalse((new LectorCsvPropio())->reconoceFilas([['Fecha', 'Concepto', 'Importe', 'Saldo']]));
        self::assertNotSame(
            LectorCsvPropio::ID,
            (new IdentificarBancoExtracto())->desdeCsv("Fecha;Concepto;Importe;Saldo\n15/01/2026;Luz;-40,00;100,00\n"),
        );
    }

    public function testUnExtractoDeBancoConLectorPropioNoSeTomaPorFormatoPropio(): void
    {
        $csv = "Data Procés,Data Valor,Codi,Concepte,Observacions,Oficina,Import,Saldo,Remesa\n"
            . "08/10/2026,08/10/2026,00007,TRANSFERÈNCIES,Factura,6051,-1.00,100.00,ES001\n";

        self::assertSame('bbva', (new IdentificarBancoExtracto())->desdeCsv($csv));
    }
}
