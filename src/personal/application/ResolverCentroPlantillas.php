<?php

declare(strict_types=1);

namespace src\personal\application;

use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\CentroRepository;
use src\personas\domain\contracts\PersonaRepository;

/**
 * Centro cuyas plantillas se ofrecen en el libro personal: el centro al que va la
 * remesa (primer vínculo de la identidad) cuando el libro está en un centro de tipo
 * «p»; si no, el propio centro del libro. Es el mismo criterio que ResolverMesRemesa.
 */
final class ResolverCentroPlantillas
{
    public function __construct(
        private readonly PersonaRepository $personas,
        private readonly CentroRepository $centros,
        private readonly IdentidadRepository $identidades,
        private readonly ?int $identidadId,
    ) {
    }

    public function ejecutar(int $personaId, int $centroLibroId): int
    {
        if ($this->identidadId === null) {
            return $centroLibroId;
        }
        $persona = $this->personas->porId($personaId);
        $centro = $persona?->centroId !== null ? $this->centros->porId($persona->centroId) : null;
        if ($centro === null || $centro->tipo !== 'p') {
            return $centroLibroId;
        }
        $vinculos = $this->identidades->personasVinculoDe($this->identidadId);

        return $vinculos === [] ? $centroLibroId : (int) $vinculos[0]['centro_id'];
    }
}
