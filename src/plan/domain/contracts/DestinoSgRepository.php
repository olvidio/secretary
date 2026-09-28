<?php

declare(strict_types=1);

namespace src\plan\domain\contracts;

interface DestinoSgRepository
{
    /**
     * Etiquetas de los destinos 42–54 que el centro ha nombrado.
     * null si el centro no es de plan H16s.
     *
     * @return array<string, string>|null codigo => etiqueta
     */
    public function nombrados(int $centroId): ?array;

    /**
     * @return list<array{codigo:string,etiqueta:string,orden:int}>
     */
    public function paraCentro(int $centroId): array;

    /**
     * @param list<array{codigo:string,etiqueta:string,orden:int}> $destinos
     */
    public function guardar(int $centroId, array $destinos): void;
}
