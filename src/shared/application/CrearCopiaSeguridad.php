<?php

declare(strict_types=1);

namespace src\shared\application;

use src\shared\infrastructure\persistence\AlmacenCopiasSeguridad;

final class CrearCopiaSeguridad
{
    public function __construct(
        private readonly AlmacenCopiasSeguridad $almacen,
    ) {
    }

    /** @return array{filename: string, bytes: int, fecha: string, database: string} */
    public function ejecutar(): array
    {
        $fila = $this->almacen->crear();

        return $fila + ['database' => $this->almacen->database()];
    }
}
