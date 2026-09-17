<?php

declare(strict_types=1);

namespace src\personal\application;

use RuntimeException;
use src\personal\domain\contracts\CopiaPersonalRepository;
use src\personal\infrastructure\persistence\AlmacenCopiasPersonal;
use src\personal\infrastructure\persistence\RutasCopiasPersonal;
use src\personas\domain\contracts\PersonaRepository;

final class CrearCopiaPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly PersonaRepository $personas,
        private readonly CopiaPersonalRepository $copias,
    ) {
    }

    /**
     * @return array{filename: string, bytes: int, fecha: string, movimientos: int}
     */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $persona = $this->personas->porId($ctx->personaId);
        if ($persona === null) {
            throw new RuntimeException(_("Persona no encontrada"));
        }
        $snapshot = $this->copias->exportar($ctx->centroId, $ctx->personaId);
        $almacen = new AlmacenCopiasPersonal(
            RutasCopiasPersonal::directorio(),
            $ctx->personaId,
            $persona->iniciales,
        );
        $fila = $almacen->crear($snapshot);

        return [
            ...$fila,
            'movimientos' => count($snapshot['asientos'] ?? []),
        ];
    }
}
