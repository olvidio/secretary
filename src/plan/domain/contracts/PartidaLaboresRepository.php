<?php

declare(strict_types=1);

namespace src\plan\domain\contracts;

interface PartidaLaboresRepository
{
    /**
     * Partidas del cap. VII para el 613 P del centro.
     *
     * @return list<array{codigo:string,etiqueta:string,orden:int}>
     */
    public function paraCentro(int $centroId): array;

    /** Siembra las seis partidas por defecto (71–76) en un centro nuevo. */
    public function sembrarPorDefecto(int $centroId): void;

    /**
     * Sustituye las partidas del cap. VII y sincroniza las cuentas P del plan maestro.
     *
     * @param list<array{codigo:string,etiqueta:string,orden:int}> $partidas
     */
    public function guardar(int $centroId, array $partidas): void;
}
