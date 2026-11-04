<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\importacion\domain\services\CatalogoBancosCsv;
use src\importacion\domain\services\IdentificarBancoExtracto;
use src\importacion\domain\services\LectorCsvArquia;

final class LectorCsvArquiaTest extends TestCase
{
    /** @return list<list<string>> */
    private function filas(): array
    {
        return [
            ['Nombre', 'Titular Ejemplo', '', '', '', ''],
            ['IBAN', 'ES00 0000 0000 0000 0000 0000', '', '', '', ''],
            ['', '', '', '', '', ''],
            ['Fecha de la operación', 'Fecha valor', 'Tipo movimiento', 'Importe', 'Saldo', 'Nro. Apunte'],
            ['46328.0416666667', '46328.0416666667', 'rcbo.parking nunez santalo s a', '-142.81', '3875.63', '8971'],
            ['46328.0416666667', '46328.0416666667', 'rcbo.parking nunez santalo s a', '-142.81', '4018.44', '8970'],
            ['46328.0416666667', '46328.0416666667', 'trf. fernando balcells canela', '500', '4507.13', '8965'],
        ];
    }

    public function testLeeFilasConFechaSerieConHora(): void
    {
        $lineas = (new LectorCsvArquia())->leerFilas($this->filas());
        self::assertCount(3, $lineas);
        self::assertSame('2026-11-02', $lineas[0]->fecha);
        self::assertSame(-14281, $lineas[0]->cents);
        self::assertSame(50000, $lineas[2]->cents);
        self::assertSame('trf. fernando balcells canela', $lineas[2]->concepto);
        self::assertNotSame($lineas[0]->huella, $lineas[1]->huella);
    }

    public function testIdentificaArquiaYCatalogo(): void
    {
        self::assertSame('arquia', (new IdentificarBancoExtracto())->desdeFilas($this->filas()));
        self::assertInstanceOf(LectorCsvArquia::class, CatalogoBancosCsv::lector('arquia'));
    }

    public function testRechazaOtroBanco(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LectorCsvArquia())->leer("Fecha;Concepto;Importe;Saldo\n01/02/2026;Bizum;-15,00;100,00\n");
    }
}
