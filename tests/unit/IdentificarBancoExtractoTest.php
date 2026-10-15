<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\importacion\domain\services\IdentificarBancoExtracto;
use src\importacion\infrastructure\excel\ExtractoCaixaBankDesdeArchivo;

final class IdentificarBancoExtractoTest extends TestCase
{
    private IdentificarBancoExtracto $identificar;

    protected function setUp(): void
    {
        $this->identificar = new IdentificarBancoExtracto();
    }

    public function testDetectaBbvaHistoricoCsv(): void
    {
        $csv = "Data Procés,Data Valor,Codi,Concepte,Observacions,Oficina,Import,Saldo,Remesa\n"
            . "08/10/2026,08/10/2026,00007,TRANSFERÈNCIES,Factura,6051,-1.00,100.00,ES001\n";
        self::assertSame('bbva', $this->identificar->desdeCsv($csv));
    }

    public function testDetectaN26(): void
    {
        $csv = "Booking Date,Partner Name,Amount (EUR)\n2026-01-02,Shop,-3.50\n";
        self::assertSame('n26', $this->identificar->desdeCsv($csv));
    }

    public function testDetectaBbvaDesdeXlsxReal(): void
    {
        $path = '/home/dani/Escriptori/estractesBancsTest/BBVA Histórico movimientos(6).xlsx';
        if (!is_readable($path)) {
            self::markTestSkipped('Fichero de prueba BBVA xlsx no disponible');
        }
        $filas = ExtractoCaixaBankDesdeArchivo::filas($path);
        self::assertSame('bbva', $this->identificar->desdeFilas($filas));
    }

    public function testRechazaFormatoDesconocido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->identificar->desdeCsv("ColA,ColB\n1,2\n");
    }
}
