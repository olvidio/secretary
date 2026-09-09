<?php

declare(strict_types=1);

namespace src\acceso\domain\entity;

use DateTimeImmutable;
use src\acceso\domain\services\PoliticaBloqueo;

final class Identidad
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $nombre,
        public readonly bool $activo,
        public readonly int $intentosFallidos,
        public readonly ?DateTimeImmutable $bloqueadoHasta,
        public readonly ?DateTimeImmutable $ultimoAcceso,
        public readonly ?string $alias = null,
    ) {
    }

    public function estaBloqueada(DateTimeImmutable $ahora): bool
    {
        return PoliticaBloqueo::estaBloqueada($this->bloqueadoHasta, $ahora);
    }
}
