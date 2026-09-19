<?php

declare(strict_types=1);

namespace src\shared\domain\services;

/** Tope de copias guardadas en el servidor (centro o libro personal). */
final class PoliticaCopiasServidor
{
    public const MAXIMO = 5;

    /**
     * @param list<array{filename: string, fecha?: string}> $copias
     */
    public static function nombreMasAntigua(array $copias): ?string
    {
        if ($copias === []) {
            return null;
        }
        $ordenadas = $copias;
        usort(
            $ordenadas,
            static function (array $a, array $b): int {
                $cmp = strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp($a['filename'], $b['filename']);
            },
        );
        $nombre = trim($ordenadas[0]['filename']);

        return $nombre !== '' ? $nombre : null;
    }
}
