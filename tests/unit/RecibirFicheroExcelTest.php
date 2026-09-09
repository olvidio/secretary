<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\importacion\infrastructure\http\RecibirFicheroExcel;
use src\shared\infrastructure\http\Request;

final class RecibirFicheroExcelTest extends TestCase
{
    public function testRechazaExtensionQueNoEsExcel(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'noexcel');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'x');
        $request = new Request('POST', '/api/centros/import', [], [], [], '', [], [
            'excel' => [
                'name' => 'malware.exe',
                'type' => 'application/octet-stream',
                'tmp_name' => $tmp,
                'error' => UPLOAD_ERR_OK,
                'size' => 1,
            ],
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('.xlsm o .xlsx');
        try {
            RecibirFicheroExcel::obligatorio($request);
        } finally {
            unlink($tmp);
        }
    }

    public function testCopiaUnXlsxLocalYSePuedeLimpiar(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        self::assertNotFalse($tmp);
        file_put_contents($tmp, 'fake-xlsx');
        $request = new Request('POST', '/api/centros/import', [], [], [], '', [], [
            'excel' => [
                'name' => 'libro.xlsx',
                'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'tmp_name' => $tmp,
                'error' => UPLOAD_ERR_OK,
                'size' => 9,
            ],
        ]);
        $path = RecibirFicheroExcel::obligatorio($request);
        self::assertFileExists($path);
        self::assertStringEndsWith('.xlsx', $path);
        self::assertSame('fake-xlsx', (string) file_get_contents($path));
        RecibirFicheroExcel::limpiar($path);
        self::assertFileDoesNotExist($path);
        unlink($tmp);
    }

    public function testOpcionalSinFicheroDevuelveNull(): void
    {
        $request = new Request('POST', '/api/centros', [], [], [], '', [], [
            'excel' => [
                'name' => '',
                'type' => '',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ],
        ]);
        self::assertNull(RecibirFicheroExcel::opcional($request));
    }
}
