<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\application\PoliticaVerificacionEmailRegistro;

final class PoliticaVerificacionEmailRegistroTest extends TestCase
{
    private ?string $anterior = null;

    protected function setUp(): void
    {
        $this->anterior = getenv('REGISTRO_AUTO_CONFIRMA_EMAIL') !== false
            ? (string) getenv('REGISTRO_AUTO_CONFIRMA_EMAIL')
            : null;
    }

    protected function tearDown(): void
    {
        if ($this->anterior === null) {
            putenv('REGISTRO_AUTO_CONFIRMA_EMAIL');
            unset($_ENV['REGISTRO_AUTO_CONFIRMA_EMAIL']);
        } else {
            putenv('REGISTRO_AUTO_CONFIRMA_EMAIL=' . $this->anterior);
            $_ENV['REGISTRO_AUTO_CONFIRMA_EMAIL'] = $this->anterior;
        }
    }

    public function testPorDefectoExigeCorreo(): void
    {
        putenv('REGISTRO_AUTO_CONFIRMA_EMAIL=0');
        $_ENV['REGISTRO_AUTO_CONFIRMA_EMAIL'] = '0';
        self::assertFalse(PoliticaVerificacionEmailRegistro::confirmaAlInstante());
    }

    public function testActivaConUno(): void
    {
        putenv('REGISTRO_AUTO_CONFIRMA_EMAIL=1');
        $_ENV['REGISTRO_AUTO_CONFIRMA_EMAIL'] = '1';
        self::assertTrue(PoliticaVerificacionEmailRegistro::confirmaAlInstante());
    }
}
