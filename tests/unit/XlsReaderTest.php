<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\personal\domain\services\LectorCsvCaixaBank;
use src\personal\infrastructure\excel\ExtractoCaixaBankDesdeArchivo;
use src\shared\infrastructure\excel\XlsReader;

final class XlsReaderTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/caixabank_moviments.xls';

    public function testLeeExtractoCaixaBankXls(): void
    {
        if (!is_readable(self::FIXTURE)) {
            self::markTestSkipped('Falta tests/fixtures/caixabank_moviments.xls');
        }
        $filas = (new XlsReader(self::FIXTURE))->filasComoTexto();
        self::assertGreaterThanOrEqual(20, count($filas));
        self::assertSame('Data', $filas[2][0]);
        self::assertSame('Import', $filas[2][4]);
        self::assertSame('2026-09-08', $filas[3][0]);
        self::assertSame('PARLEM TELECOM', $filas[3][2]);
        self::assertSame('-21.38', $filas[3][4]);

        $lineas = (new LectorCsvCaixaBank())->leerFilas(ExtractoCaixaBankDesdeArchivo::filas(self::FIXTURE));
        self::assertGreaterThanOrEqual(18, count($lineas));
        self::assertSame('2026-09-08', $lineas[0]->fecha);
        self::assertSame(-2138, $lineas[0]->cents);
        self::assertStringContainsString('PARLEM TELECOM', $lineas[0]->concepto);
        self::assertStringContainsString('Rebuts varis', $lineas[0]->concepto);
    }
}
