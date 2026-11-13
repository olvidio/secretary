<?php

declare(strict_types=1);

namespace src\listados\domain\services;

use InvalidArgumentException;

/**
 * Grupos de un listado según el texto de la glosa: cada grupo tiene un nombre y un patrón.
 * El patrón es una expresión regular sin distinguir mayúsculas; varias alternativas se
 * separan con «|» (gas = Gas Natural | NATURGY). Un movimiento va al primer grupo que casa.
 */
final class GruposGlosa
{
    private const DELIMITADOR = "\x01";

    /**
     * Limpia la lista y rechaza los patrones que no son una expresión regular válida.
     *
     * @return list<array{nombre:string,patron:string}>
     */
    public static function normalizar(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = json_decode($valor, true);
        }
        if (!is_array($valor)) {
            return [];
        }
        $out = [];
        foreach ($valor as $grupo) {
            if (!is_array($grupo)) {
                continue;
            }
            $nombre = self::limpio((string) ($grupo['nombre'] ?? ''));
            $patron = self::limpio((string) ($grupo['patron'] ?? ''));
            if ($nombre === '' && $patron === '') {
                continue;
            }
            if ($patron === '') {
                $patron = $nombre;
            }
            if ($nombre === '') {
                $nombre = $patron;
            }
            if (self::regex($patron) === null) {
                throw new InvalidArgumentException(sprintf(
                    _('El patrón del grupo «%s» no es una expresión regular válida'),
                    $nombre
                ));
            }
            $out[] = ['nombre' => $nombre, 'patron' => $patron];
        }

        return $out;
    }

    /**
     * Posición del primer grupo cuyo patrón aparece en la glosa; null si no casa ninguno.
     *
     * @param list<array{nombre:string,patron:string}> $grupos
     */
    public static function indiceDe(string $glosa, array $grupos): ?int
    {
        foreach ($grupos as $i => $grupo) {
            $regex = self::regex($grupo['patron']);
            if ($regex !== null && preg_match($regex, $glosa) === 1) {
                return $i;
            }
        }

        return null;
    }

    /** Expresión lista para preg_match, o null si el patrón no compila. */
    private static function regex(string $patron): ?string
    {
        $alternativas = [];
        foreach (explode('|', $patron) as $parte) {
            $parte = trim($parte);
            // Se admiten las alternativas entre comillas: 'Gas Natural' | 'NATURGY'.
            if (strlen($parte) >= 2 && ($parte[0] === "'" || $parte[0] === '"') && str_ends_with($parte, $parte[0])) {
                $parte = trim(substr($parte, 1, -1));
            }
            if ($parte !== '') {
                $alternativas[] = $parte;
            }
        }
        if ($alternativas === []) {
            return null;
        }
        $regex = self::DELIMITADOR . implode('|', $alternativas) . self::DELIMITADOR . 'iu';

        return @preg_match($regex, '') === false ? null : $regex;
    }

    private static function limpio(string $texto): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]/', ' ', $texto));
    }
}
