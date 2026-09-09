<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\personal\application\ResolverPersonaActual;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\contracts\RemesaRepository;

final class ObtenerRemesaPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly RemesaRepository $remesas,
        private readonly PersonaRepository $personas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $id): array
    {
        $ctx = $this->ambito->ejecutar();
        $remesa = $this->remesas->porId($id);
        if ($remesa === null || $remesa->personaId !== $ctx->personaId) {
            throw new InvalidArgumentException('Remesa no encontrada');
        }
        $persona = $this->personas->porId($ctx->personaId);
        $out = $remesa->toArray();
        $out['iniciales'] = $persona?->iniciales;
        $out['persona'] = $persona?->nombreCompleto();

        return $out;
    }
}
