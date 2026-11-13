<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\listados\domain\services\GruposGlosa;

final class GruposGlosaTest extends TestCase
{
    public function testVariasAlternativasSinDistinguirMayusculas(): void
    {
        $grupos = GruposGlosa::normalizar([
            ['nombre' => 'gas', 'patron' => "'gas Natural' | 'NATURGY'"],
            ['nombre' => 'telf', 'patron' => 'Telefono | YOIGO'],
        ]);

        self::assertSame(0, GruposGlosa::indiceDe('Recibo Naturgy enero', $grupos));
        self::assertSame(0, GruposGlosa::indiceDe('GAS NATURAL factura', $grupos));
        self::assertSame(1, GruposGlosa::indiceDe('yoigo fibra', $grupos));
        self::assertNull(GruposGlosa::indiceDe('Aigües de Barcelona', $grupos));
    }

    public function testAdmiteExpresionRegularYAcentos(): void
    {
        $grupos = GruposGlosa::normalizar([
            ['nombre' => 'aigua', 'patron' => 'aig[uü]es?\b|agbar'],
            ['nombre' => 'telf', 'patron' => 'tel[eè]fon'],
        ]);

        self::assertSame(0, GruposGlosa::indiceDe('Aigües de Barcelona', $grupos));
        self::assertSame(1, GruposGlosa::indiceDe('TELÈFON mòbil', $grupos));
    }

    public function testSinPatronUsaElNombreYDescartaVacios(): void
    {
        $grupos = GruposGlosa::normalizar([
            ['nombre' => 'Endesa', 'patron' => ''],
            ['nombre' => ' ', 'patron' => ''],
        ]);

        self::assertSame([['nombre' => 'Endesa', 'patron' => 'Endesa']], $grupos);
    }

    public function testRechazaPatronNoValido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GruposGlosa::normalizar([['nombre' => 'gas', 'patron' => 'gas (natural']]);
    }
}
