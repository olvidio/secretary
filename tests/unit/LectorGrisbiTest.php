<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\grisbi\domain\services\LectorGrisbi;

final class LectorGrisbiTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/grisbi_minimo.xml';

    public function testParseaFixtureMinimo(): void
    {
        $xml = file_get_contents(self::FIXTURE);
        self::assertIsString($xml);
        $libro = (new LectorGrisbi())->leer($xml);

        self::assertCount(2, $libro->cuentas);
        self::assertSame('caja', $libro->cuentas[0]->tipo);
        self::assertSame('banco', $libro->cuentas[1]->tipo);

        self::assertCount(2, $libro->movimientos);
        self::assertSame('2024-03-15', $libro->movimientos[0]->fecha);
        self::assertSame(-1050, $libro->movimientos[0]->importeCents);
        self::assertSame(1, $libro->movimientos[0]->subcategoria);
        self::assertSame(2000, $libro->movimientos[1]->importeCents);

        self::assertCount(1, $libro->subcategorias);
        self::assertSame('Papel', $libro->subcategorias[0]->nombre);
        self::assertSame(60, $libro->subcategorias[0]->categoria);

        self::assertCount(1, $libro->listados);
        self::assertSame('Cuotas', $libro->listados[0]->nombre);
        self::assertSame([['categoria' => 70, 'subcategoria' => null]], $libro->listados[0]->categorias);
        self::assertSame([1], $libro->listados[0]->terceros);
        self::assertTrue($libro->listados[0]->mostrarMovimientos);
    }

    public function testRechazaXmlSinRaizGrisbi(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LectorGrisbi())->leer('<root/>');
    }

    public function testRechazaXmlVacio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LectorGrisbi())->leer('');
    }
}
