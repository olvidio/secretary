<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\EntradaPeriodicaRepository;

final class BorrarEntradaPeriodica
{
    public function __construct(
        private readonly EntradaPeriodicaRepository $entradas,
        private readonly ResolverAmbitoActual $ambito,
        private readonly ComprobarAccesoCentroSg $centroSg,
    ) {
    }

    public function ejecutar(int $id): void
    {
        $this->centroSg->ejecutar();
        $ctx = $this->ambito->ejecutar();
        if ($this->entradas->porId($ctx->centroId, $id) === null) {
            throw new InvalidArgumentException(_("Entrada periódica no encontrada"));
        }
        $this->entradas->borrar($ctx->centroId, $id);
    }
}
