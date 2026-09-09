<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\PlantillaApunteRepository;

final class BorrarPlantillaApunte
{
    public function __construct(
        private readonly PlantillaApunteRepository $plantillas,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    public function ejecutar(int $id): void
    {
        $ctx = $this->ambito->ejecutar();
        if ($this->plantillas->porId($ctx->centroId, $id) === null) {
            throw new InvalidArgumentException('Plantilla no encontrada');
        }
        $this->plantillas->borrar($ctx->centroId, $id);
    }
}
