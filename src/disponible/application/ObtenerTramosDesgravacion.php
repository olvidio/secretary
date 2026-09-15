<?php

declare(strict_types=1);

namespace src\disponible\application;

use src\ambito\application\ResolverAmbitoActual;
use src\disponible\domain\contracts\TramosDesgravacionRepository;

final class ObtenerTramosDesgravacion
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly TramosDesgravacionRepository $tramos,
    ) {
    }

    /** @return list<array{hasta_cents:?int, porcentaje:int}> */
    public function ejecutar(): array
    {
        return $this->tramos->deCentro($this->ambito->ejecutar()->centroId);
    }
}
