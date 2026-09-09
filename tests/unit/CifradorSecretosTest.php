<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\infrastructure\crypto\CifradorSecretos;

final class CifradorSecretosTest extends TestCase
{
    public function testIdaYVuelta(): void
    {
        $c = new CifradorSecretos('clave-de-prueba-unitaria');
        $claro = 'JBSWY3DPEHPK3PXP';
        $paquete = $c->cifrar($claro);
        self::assertNotSame($claro, $paquete);
        self::assertSame($claro, $c->descifrar($paquete));
        self::assertNotSame($paquete, $c->cifrar($claro));
    }
}
