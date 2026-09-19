<?php

declare(strict_types=1);

namespace src\shared\application;

use src\shared\domain\exceptions\LimiteCopiasAlcanzado;
use src\shared\domain\services\PoliticaCopiasServidor;

final class AsegurarHuecoCopias
{
    /**
     * @param list<array{filename: string, fecha?: string}> $copias
     * @param callable(string): void $borrar
     */
    public static function ejecutar(array $copias, callable $borrar, bool $borrarMasAntigua): void
    {
        if (count($copias) < PoliticaCopiasServidor::MAXIMO) {
            return;
        }
        if (!$borrarMasAntigua) {
            throw new LimiteCopiasAlcanzado();
        }
        $restantes = $copias;
        while (count($restantes) >= PoliticaCopiasServidor::MAXIMO) {
            $nombre = PoliticaCopiasServidor::nombreMasAntigua($restantes);
            if ($nombre === null) {
                return;
            }
            $borrar($nombre);
            $restantes = array_values(array_filter(
                $restantes,
                static fn (array $c): bool => $c['filename'] !== $nombre,
            ));
        }
    }
}
