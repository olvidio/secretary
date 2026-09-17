<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use src\personal\infrastructure\persistence\AlmacenCopiasPersonal;
use src\personal\infrastructure\persistence\RutasCopiasPersonal;
use src\personas\domain\contracts\PersonaRepository;

final class BorrarCopiaPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly PersonaRepository $personas,
    ) {
    }

    public function ejecutar(array $datos): void
    {
        $nombre = trim((string) ($datos['fichero'] ?? ''));
        if ($nombre === '') {
            throw new InvalidArgumentException(_("Indique el fichero a borrar"));
        }
        $ctx = $this->ambito->ejecutar();
        $persona = $this->personas->porId($ctx->personaId);
        if ($persona === null) {
            throw new InvalidArgumentException(_("Persona no encontrada"));
        }
        $almacen = new AlmacenCopiasPersonal(
            RutasCopiasPersonal::directorio(),
            $ctx->personaId,
            $persona->iniciales,
        );
        $almacen->borrarPorNombre($nombre);
    }
}
