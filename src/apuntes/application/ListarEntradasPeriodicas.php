<?php

declare(strict_types=1);

namespace src\apuntes\application;

use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\EntradaPeriodicaRepository;

final class ListarEntradasPeriodicas
{
    public function __construct(
        private readonly EntradaPeriodicaRepository $entradas,
        private readonly ResolverAmbitoActual $ambito,
        private readonly ComprobarAccesoCentroSg $centroSg,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(): array
    {
        $this->centroSg->ejecutar();
        $ctx = $this->ambito->ejecutar();
        $out = [];
        foreach ($this->entradas->listar($ctx->centroId) as $e) {
            $out[] = $e->toArray();
        }

        return $out;
    }
}
