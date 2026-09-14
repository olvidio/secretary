<?php

declare(strict_types=1);

namespace src\shared\application;

use InvalidArgumentException;
use src\shared\infrastructure\persistence\AlmacenCopiasSeguridad;

final class RestaurarCopiaSeguridad
{
    public function __construct(
        private readonly AlmacenCopiasSeguridad $almacen,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos, ?string $ficheroSubido = null): void
    {
        if (empty($datos['confirmar'])) {
            throw new InvalidArgumentException('Confirme la restauración con confirmar: true');
        }
        if ($ficheroSubido !== null && $ficheroSubido !== '') {
            $this->almacen->restaurarTemporal($ficheroSubido);

            return;
        }
        $nombre = trim((string) ($datos['fichero'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException('Indique un fichero de copia o súbalo');
        }
        $this->almacen->restaurarPorNombre($nombre);
    }
}
