<?php

declare(strict_types=1);

namespace src\disponible\domain\contracts;

interface TramosDesgravacionRepository
{
    /**
     * @return list<array{hasta_cents:?int, porcentaje:int}>
     */
    public function deCentro(int $centroId): array;

    /**
     * @param list<array{hasta_cents:?int, porcentaje:int}> $tramos
     */
    public function guardar(int $centroId, array $tramos): void;
}
