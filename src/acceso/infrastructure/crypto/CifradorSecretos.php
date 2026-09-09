<?php

declare(strict_types=1);

namespace src\acceso\infrastructure\crypto;

use RuntimeException;
use src\acceso\domain\contracts\CifradorSecretos as CifradorSecretosContrato;
use src\shared\infrastructure\persistence\ConnectionFactory;

final class CifradorSecretos implements CifradorSecretosContrato
{
    public function __construct(private readonly string $clave)
    {
        if ($this->clave === '' || strlen($this->material()) !== 32) {
            throw new RuntimeException('APP_KEY no produce una clave de 32 bytes');
        }
    }

    public static function desdeEntorno(): self
    {
        return new self(self::pimiento());
    }

    /** Pimiento de hashes de recuperación: el mismo APP_KEY que cifra TOTP. */
    public static function pimiento(): string
    {
        $raw = ConnectionFactory::env('APP_KEY', '') ?: '';
        if ($raw === '') {
            throw new RuntimeException(
                'Falta APP_KEY en el entorno. Añádala a .env (32+ caracteres aleatorios) para cifrar secretos TOTP.'
            );
        }

        return $raw;
    }

    public function cifrar(string $claro): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($claro, 'aes-256-gcm', $this->material(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cifrado === false || strlen($tag) !== 16) {
            throw new RuntimeException('No se pudo cifrar el secreto TOTP');
        }

        return base64_encode($iv . $tag . $cifrado);
    }

    public function descifrar(string $paquete): string
    {
        $raw = base64_decode($paquete, true);
        if ($raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Secreto TOTP corrupto');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cifrado = substr($raw, 28);
        $claro = openssl_decrypt($cifrado, 'aes-256-gcm', $this->material(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($claro === false) {
            throw new RuntimeException('No se pudo descifrar el secreto TOTP');
        }

        return $claro;
    }

    private function material(): string
    {
        return hash('sha256', $this->clave, true);
    }
}
