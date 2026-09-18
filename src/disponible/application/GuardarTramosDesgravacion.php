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
     * @return array{tramos: list<array{hasta_cents:?int, porcentaje:int}>, maximo_pct: int}
     */
    public function ejecutar(array $datos): array
    {
        $raw = $datos['tramos'] ?? TramosDesgravacion::porDefecto();
        $pack = TramosDesgravacion::empaquetar(
            is_array($raw) ? $raw : [],
            $datos['maximo_pct'] ?? TramosDesgravacion::MAXIMO_PCT_POR_DEFECTO,
        );
        $this->tramos->guardar($this->ambito->ejecutar()->centroId, $pack['tramos'], $pack['maximo_pct']);

        return $pack;
    }
}
