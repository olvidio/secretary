<?php

declare(strict_types=1);

namespace src\acceso\domain\entity;

final class VinculoCentro
{
    public function __construct(
        public readonly int $centroId,
        public readonly string $rol,
        public readonly string $codigo,
        public readonly string $nombre,
    ) {
    }
}
