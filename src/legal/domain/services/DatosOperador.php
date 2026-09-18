<?php

declare(strict_types=1);

namespace src\legal\domain\services;

final class DatosOperador
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $email,
        public readonly string $direccion,
    ) {
    }

    /**
     * @return array{nombre: string, email: string, direccion: string}
     */
    public function toArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'email' => $this->email,
            'direccion' => $this->direccion,
        ];
    }
}
