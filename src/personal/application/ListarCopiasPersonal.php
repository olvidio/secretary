<?php

declare(strict_types=1);

namespace src\personal\application;

use RuntimeException;
use src\personal\infrastructure\persistence\AlmacenCopiasPersonal;
use src\personal\infrastructure\persistence\RutasCopiasPersonal;
use src\personas\domain\contracts\PersonaRepository;

final class ListarCopiasPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly PersonaRepository $personas,
    ) {
    }

    /**
     * @return array{copias: list<array{filename: string, bytes: int, fecha: string}>, iniciales: string}
     */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $persona = $this->personas->porId($ctx->personaId);
        if ($persona === null) {
            throw new RuntimeException('Persona no encontrada');
        }
        $almacen = new AlmacenCopiasPersonal(
            RutasCopiasPersonal::directorio(),
            $ctx->personaId,
            $persona->iniciales,
        );

        return [
            'copias' => $almacen->listar(),
            'iniciales' => $persona->iniciales,
        ];
    }
}
