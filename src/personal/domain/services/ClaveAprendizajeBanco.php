<?php

declare(strict_types=1);

namespace src\personal\domain\services;

/** Clave estable del beneficiario de un extracto, para sugerir categoría. */
final class ClaveAprendizajeBanco
{
    /**
     * Beneficiario normalizado (sin importe, cifras ni signos).
     * "CAPRABO 7776" y "CAPRABO 7851" dan la misma clave.
     */
    public static function de(string $concepto): string
    {
        $payee = trim(explode('·', $concepto, 2)[0]);
        $payee = mb_strtolower($payee);
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $payee);
        if (is_string($trans) && $trans !== '') {
            $payee = $trans;
        }
        $payee = strtr($payee, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $payee = preg_replace('/\d+/', ' ', $payee) ?? $payee;
        $payee = preg_replace('/[^a-z]+/', ' ', $payee) ?? $payee;

        return trim(preg_replace('/\s+/', ' ', $payee) ?? $payee);
    }

    /**
     * Núcleo más corto: primer token largo o los dos primeros significativos.
     * "CURSOR, AI POWERED IDE" y "CURSOR USAGE JUL" → "cursor".
     */
    public static function nucleo(string $concepto): string
    {
        $clave = self::de($concepto);
        if ($clave === '') {
            return '';
        }
        $stop = ['el', 'la', 'los', 'las', 'de', 'del', 'the', 'a', 'un', 'una', 'and', 'i', 'y', 'www'];
        $partes = [];
        foreach (explode(' ', $clave) as $p) {
            if ($p !== '' && !in_array($p, $stop, true)) {
                $partes[] = $p;
            }
        }
        if ($partes === []) {
            return $clave;
        }
        if (strlen($partes[0]) >= 5) {
            return $partes[0];
        }

        return implode(' ', array_slice($partes, 0, 2));
    }
}
