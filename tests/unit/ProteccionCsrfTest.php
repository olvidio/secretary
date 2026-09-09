<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\infrastructure\http\ProteccionCsrf;
use src\shared\infrastructure\http\Request;

final class ProteccionCsrfTest extends TestCase
{
    public function testAceptaCampoYCabecera(): void
    {
        $token = 'a1b2c3d4e5f6789012345678901234567890abcdabcdabcdabcdabcdabcdabcd';
        $porCampo = new Request('POST', '/login', [], ['_csrf' => $token], ['csrf' => $token]);
        self::assertTrue(ProteccionCsrf::valido($porCampo));
        $porCabecera = new Request(
            'POST',
            '/api/apuntes',
            [],
            [],
            ['csrf' => $token],
            '',
            ['x-csrf-token' => $token],
        );
        self::assertTrue(ProteccionCsrf::valido($porCabecera));
        $malo = new Request('POST', '/login', [], ['_csrf' => 'otro'], ['csrf' => $token]);
        self::assertFalse(ProteccionCsrf::valido($malo));
    }

    public function testRenovarTokenGeneraValorDistinto(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['csrf'] = 'anterior';
        $nuevo = ProteccionCsrf::renovarToken();
        self::assertNotSame('anterior', $nuevo);
        self::assertSame($nuevo, $_SESSION['csrf']);
    }
}
