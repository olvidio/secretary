<?php

declare(strict_types=1);

namespace src\ayuda\domain\services;

/**
 * Texto en minúsculas y sin acentos. El usuario escribe «como» y «cómo» sin
 * distinguir, así que ni la búsqueda ni la reutilización de respuestas deben
 * distinguirlo tampoco.
 */
final class NormalizadorTexto
{
    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    public static function plano(string $texto): string
    {
        return strtr(mb_strtolower($texto, 'UTF-8'), self::ACENTOS);
    }
}
