<?php

declare(strict_types=1);

namespace src\apuntes\domain\contracts;

interface CentroBancoRepository
{
    public function deCentro(int $centroId): ?string;

    public function guardar(int $centroId, string $banco): void;
}
