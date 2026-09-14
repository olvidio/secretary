<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\ayuda\infrastructure\llm\ClienteChatCompatibleOpenAI;

final class ClienteChatCompatibleOpenAITest extends TestCase
{
    /** @var array<string, string|false> */
    private array $previo = [];

    protected function tearDown(): void
    {
        foreach (['AYUDA_IA_CLAVE', 'AYUDA_IA_URL', 'AYUDA_IA_MODELO'] as $clave) {
            $antes = $this->previo[$clave] ?? false;
            if ($antes === false) {
                putenv($clave);
                unset($_ENV[$clave]);
            } else {
                putenv($clave . '=' . $antes);
                $_ENV[$clave] = $antes;
            }
        }
        $this->previo = [];
    }

    public function testSinClaveNoHayProveedor(): void
    {
        $this->poner('AYUDA_IA_CLAVE', '');
        $this->poner('AYUDA_IA_URL', '');
        $this->poner('AYUDA_IA_MODELO', '');

        self::assertNull(ClienteChatCompatibleOpenAI::desdeEntorno());
    }

    public function testConClaveUsaGeminiGratuitoPorDefecto(): void
    {
        $this->poner('AYUDA_IA_CLAVE', 'clave-de-prueba');
        $this->poner('AYUDA_IA_URL', '');
        $this->poner('AYUDA_IA_MODELO', '');

        $cliente = ClienteChatCompatibleOpenAI::desdeEntorno();
        self::assertInstanceOf(ClienteChatCompatibleOpenAI::class, $cliente);
        self::assertSame(ClienteChatCompatibleOpenAI::URL_GEMINI, $cliente->url);
        self::assertSame(ClienteChatCompatibleOpenAI::MODELO_GEMINI, $cliente->modelo);
    }

    private function poner(string $clave, string $valor): void
    {
        if (!array_key_exists($clave, $this->previo)) {
            $this->previo[$clave] = getenv($clave);
        }
        if ($valor === '') {
            putenv($clave);
            unset($_ENV[$clave]);

            return;
        }
        putenv($clave . '=' . $valor);
        $_ENV[$clave] = $valor;
    }
}
