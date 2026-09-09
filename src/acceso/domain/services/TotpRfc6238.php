<?php

declare(strict_types=1);

namespace src\acceso\domain\services;

/**
 * TOTP según RFC 6238 (HMAC-SHA1, 30 s, 6 dígitos) y HOTP RFC 4226.
 * Sin dependencias externas.
 */
final class TotpRfc6238
{
    public const PERIODO_SEGUNDOS = 30;
    public const DIGITOS = 6;
    public const VENTANA = 1;

    public static function secretoAleatorio(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function codigo(string $secretBase32, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $contador = intdiv($timestamp, self::PERIODO_SEGUNDOS);

        return self::hotp($secretBase32, $contador);
    }

    public static function verificar(
        string $secretBase32,
        string $codigo,
        int $ventana = self::VENTANA,
        ?int $timestamp = null,
    ): bool {
        $codigo = preg_replace('/\s+/', '', $codigo) ?? '';
        if (!preg_match('/^\d{6}$/', $codigo)) {
            return false;
        }
        $timestamp ??= time();
        $contador = intdiv($timestamp, self::PERIODO_SEGUNDOS);
        for ($i = -$ventana; $i <= $ventana; $i++) {
            if (hash_equals(self::hotp($secretBase32, $contador + $i), $codigo)) {
                return true;
            }
        }

        return false;
    }

    public static function otpauthUri(string $cuenta, string $secretBase32, string $emisor = 'Secretario'): string
    {
        $label = rawurlencode($emisor . ':' . $cuenta);
        $query = http_build_query([
            'secret' => $secretBase32,
            'issuer' => $emisor,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITOS,
            'period' => self::PERIODO_SEGUNDOS,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/' . $label . '?' . $query;
    }

    public static function hotp(string $secretBase32, int $contador): string
    {
        $key = self::base32Decode($secretBase32);
        $bin = pack('N*', 0) . pack('N*', $contador);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $unpacked = unpack('N', substr($hash, $offset, 4));
        if ($unpacked === false) {
            throw new \RuntimeException('No se pudo truncar HMAC');
        }
        $truncated = $unpacked[1] & 0x7fffffff;
        $mod = 10 ** self::DIGITOS;
        $otp = $truncated % $mod;

        return str_pad((string) $otp, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $chunks = str_split($binary, 5);
        $out = '';
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0');
            }
            $out .= $alphabet[bindec($chunk)];
        }

        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        $len = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos($alphabet, $b32[$i]);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = str_split($binary, 8);
        $out = '';
        foreach ($bytes as $byte) {
            if (strlen($byte) < 8) {
                break;
            }
            $out .= chr((int) bindec($byte));
        }

        return $out;
    }
}
