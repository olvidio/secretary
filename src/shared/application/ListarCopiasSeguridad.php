<?php

declare(strict_types=1);

namespace src\shared\application;

use src\shared\infrastructure\persistence\AlmacenCopiasSeguridad;

final class ListarCopiasSeguridad
{
    public function __construct(
        private readonly AlmacenCopiasSeguridad $almacen,
    ) {
    }

    /**
     * @return array{copias: list<array{filename: string, bytes: int, fecha: string}>, database: string}
     */
    public function ejecutar(): array
    {
        return [
            'copias' => $this->almacen->listar(),
            'database' => $this->almacen->database(),
        ];
    }
}
