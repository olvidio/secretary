<?php

declare(strict_types=1);

namespace src\shared\application;

use InvalidArgumentException;
use src\shared\infrastructure\persistence\AlmacenCopiasSeguridad;

final class BorrarCopiaSeguridad
{
    public function __construct(
        private readonly AlmacenCopiasSeguridad $almacen,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): void
    {
        $nombre = trim((string) ($datos['fichero'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException(_("Indique el fichero a borrar"));
        }
        $this->almacen->borrarPorNombre($nombre);
    }
}
