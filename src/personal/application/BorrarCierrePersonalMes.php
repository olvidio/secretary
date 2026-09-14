<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use src\personal\domain\contracts\PersonalCierreRepository;
use src\personal\domain\services\PeriodoPersonal;

final class BorrarCierrePersonalMes
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly PersonalCierreRepository $cierres,
        private readonly ResolverPeriodoPersonal $periodo,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $anio = (int) ($datos['anio'] ?? 0);
        $mes = (int) ($datos['mes'] ?? 0);
        PeriodoPersonal::validar($anio, $mes);
        $this->cierres->borrarMes($ctx->personaId, $anio, $mes);

        return $this->periodo->ejecutar($ctx->personaId, $anio, $mes);
    }
}
