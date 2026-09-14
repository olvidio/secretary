<?php

declare(strict_types=1);

namespace src\ayuda\infrastructure\llm;

use JsonException;
use RuntimeException;
use src\ayuda\domain\contracts\ProveedorRespuestaIA;
use src\shared\infrastructure\persistence\ConnectionFactory;

/**
 * Cliente del formato «chat completions» de OpenAI, que es el que hablan
 * Gemini, Groq, OpenRouter, Mistral, DeepSeek, Cloudflare y Ollama.
 *
 * Por defecto (local): Gemini 3.5 Flash-Lite en el nivel gratuito de AI Studio.
 * Basta con `AYUDA_IA_CLAVE`. URL y modelo se cambian solo si se usa otro
 * proveedor.
 */
final class ClienteChatCompatibleOpenAI implements ProveedorRespuestaIA
{
    public const URL_GEMINI = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
    public const MODELO_GEMINI = 'gemini-3.5-flash-lite';

    private const MAXIMO_TOKENS = 700;

    public function __construct(
        public readonly string $url,
        public readonly string $modelo,
        private readonly string $clave,
        private readonly int $timeout = 25,
    ) {
    }

    /** Null si falta la clave: la ayuda funciona igual, sin IA. */
    public static function desdeEntorno(): ?self
    {
        $clave = trim((string) ConnectionFactory::env('AYUDA_IA_CLAVE', ''));
        if ($clave === '') {
            return null;
        }
        $url = trim((string) ConnectionFactory::env('AYUDA_IA_URL', self::URL_GEMINI));
        $modelo = trim((string) ConnectionFactory::env('AYUDA_IA_MODELO', self::MODELO_GEMINI));
        if ($url === '') {
            $url = self::URL_GEMINI;
        }
        if ($modelo === '') {
            $modelo = self::MODELO_GEMINI;
        }
        $timeout = (int) ConnectionFactory::env('AYUDA_IA_TIMEOUT', '25');

        return new self($url, $modelo, $clave, $timeout > 0 ? $timeout : 25);
    }

    public static function limiteDiario(): int
    {
        return (int) ConnectionFactory::env('AYUDA_IA_LIMITE_DIARIO', '30');
    }

    public function responder(string $instruccion, string $pregunta): string
    {
        try {
            $cuerpo = json_encode([
                'model' => $this->modelo,
                'temperature' => 0,
                'max_tokens' => self::MAXIMO_TOKENS,
                'messages' => [
                    ['role' => 'system', 'content' => $instruccion],
                    ['role' => 'user', 'content' => $pregunta],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('No se pudo preparar la consulta a la IA: ' . $e->getMessage());
        }
        $respuesta = function_exists('curl_init')
            ? $this->porCurl($cuerpo)
            : $this->porStream($cuerpo);

        return $this->contenido($respuesta);
    }

    /** @return array{0: int, 1: string} */
    private function porCurl(string $cuerpo): array
    {
        $ch = curl_init($this->url);
        if ($ch === false) {
            throw new RuntimeException('No se pudo contactar con el servicio de IA');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $cuerpo,
            CURLOPT_HTTPHEADER => $this->cabeceras(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $salida = curl_exec($ch);
        $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($salida)) {
            throw new RuntimeException('El servicio de IA no respondió' . ($error !== '' ? ': ' . $error : ''));
        }

        return [$estado, $salida];
    }

    /** @return array{0: int, 1: string} */
    private function porStream(string $cuerpo): array
    {
        $contexto = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $this->cabeceras()),
                'content' => $cuerpo,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);
        $salida = @file_get_contents($this->url, false, $contexto);
        if (!is_string($salida)) {
            throw new RuntimeException('El servicio de IA no respondió');
        }
        $estado = 0;
        foreach ($http_response_header as $cabecera) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $cabecera, $coincidencias) === 1) {
                $estado = (int) $coincidencias[1];
            }
        }

        return [$estado, $salida];
    }

    /** @return list<string> */
    private function cabeceras(): array
    {
        return [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->clave,
        ];
    }

    /** @param array{0: int, 1: string} $respuesta */
    private function contenido(array $respuesta): string
    {
        [$estado, $salida] = $respuesta;
        $datos = json_decode($salida, true);
        if (!is_array($datos)) {
            throw new RuntimeException('El servicio de IA devolvió una respuesta ilegible');
        }
        if (array_is_list($datos) && isset($datos[0]) && is_array($datos[0])) {
            $datos = $datos[0];
        }
        if ($estado >= 400) {
            $mensaje = $datos['error']['message'] ?? $datos['error'] ?? 'error ' . $estado;

            throw new RuntimeException(
                'El servicio de IA rechazó la consulta: ' . (is_string($mensaje) ? $mensaje : 'error ' . $estado),
            );
        }
        $contenido = $datos['choices'][0]['message']['content'] ?? null;
        if (!is_string($contenido) || trim($contenido) === '') {
            throw new RuntimeException('El servicio de IA no devolvió ninguna respuesta');
        }

        return $contenido;
    }
}
