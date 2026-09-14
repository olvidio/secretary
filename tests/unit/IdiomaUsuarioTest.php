<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\value_objects\IdiomaUsuario;

final class IdiomaUsuarioTest extends TestCase
{
    public function testAceptaEsYCa(): void
    {
        self::assertSame('es', IdiomaUsuario::porDefecto()->valor);
        self::assertSame('ca', (new IdiomaUsuario('ca'))->valor);
        self::assertSame('es', IdiomaUsuario::desde('')->valor);
    }

    public function testRechazaValorDesconocido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IdiomaUsuario('en');
    }
}
