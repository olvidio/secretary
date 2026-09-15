<?php

declare(strict_types=1);

namespace src\disponible\application;

use src\ambito\application\ResolverAmbitoActual;
use src\disponible\domain\contracts\TramosDesgravacionRepository;
use src\disponible\domain\services\TramosDesgravacion;

final class GuardarTramosDesgravacion
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly TramosDesgravacionRepository $tramos,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<array{hasta_cents:?int, porcentaje:int}>
     */
    public function ejecutar(array $datos): array
    {
        $raw = $datos['tramos'] ?? TramosDesgravacion::porDefecto();
        $norm = TramosDesgravacion::normalizar($raw);
        $this->tramos->guardar($this->ambito->ejecutar()->centroId, $norm);

        return $norm;
    }
}
