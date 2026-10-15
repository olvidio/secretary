<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\importacion\domain\services\CatalogoBancosCsv;
use src\importacion\domain\services\IdentificarBancoExtracto;
use src\importacion\domain\services\LectorCsvSantander;
use src\importacion\infrastructure\excel\ExtractoCaixaBankDesdeArchivo;

final class LectorCsvSantanderTest extends TestCase
{
    public function testCatalogoIncluyeSantander(): void
    {
        self::assertInstanceOf(LectorCsvSantander::class, CatalogoBancosCsv::lector('santander'));
    }

    public function testLeeFilasConMetadatosYMovimientos(): void
    {
        $filas = [
            ['', '', 'CUENTA SANTANDER', 'FECHA', ''],
            ['', '', 'ES7500491247012110215958', '15/10/2026 | 17:51:47', ''],
            ['', '', 'Titular', 'Saldo', ''],
            ['Movimientos', '', '', '', ''],
            ['01/10/2026', '01/10/2026', 'Recibo O2 Fibra', '-30', '107224.44'],
            ['23/09/2026', '23/09/2026', 'Pension Seguridad Social', '1508.55', '107367.9'],
        ];
        $lineas = (new LectorCsvSantander())->leerFilas($filas);
        self::assertCount(2, $lineas);
        self::assertSame('2026-10-01', $lineas[0]->fecha);
        self::assertSame(-3000, $lineas[0]->cents);
        self::assertSame('gasto', $lineas[0]->sentido());
        self::assertSame('Recibo O2 Fibra', $lineas[0]->concepto);
        self::assertSame(150855, $lineas[1]->cents);
        self::assertSame('ingreso', $lineas[1]->sentido());
    }

    public function testDetectaDesdeXlsxReal(): void
    {
        $path = '/home/dani/Escriptori/estractesBancsTest/transactions_2026-10-15T15_51_47.502Z.xls';
        if (!is_readable($path)) {
            self::markTestSkipped('Fichero Santander xls no disponible');
        }
        $filas = ExtractoCaixaBankDesdeArchivo::filas($path);
        self::assertSame('santander', (new IdentificarBancoExtracto())->desdeFilas($filas));
        $lineas = (new LectorCsvSantander())->leerFilas($filas);
        self::assertCount(22, $lineas);
        $o2 = array_values(array_filter(
            $lineas,
            static fn ($l) => $l->fecha === '2026-10-01' && $l->cents === -3000,
        ));
        self::assertCount(1, $o2);
        self::assertStringContainsString('O2 Fibra', $o2[0]->concepto);
    }

    public function testRechazaSinMarcaSantander(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LectorCsvSantander())->leerFilas([
            ['Fecha', 'Concepto', 'Importe'],
            ['01/01/2026', 'Test', '-1'],
        ]);
    }
}
