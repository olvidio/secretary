<?php

declare(strict_types=1);

namespace src\plan\application;

use src\ambito\application\ResolverAmbitoActual;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class ListarPartidasLabores
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly PartidaLaboresRepository $partidas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();

        return [
            'plan_contable' => CatalogoPlanesContables::H16N,
            'partidas' => $this->partidas->paraCentro($ctx->centroId),
        ];
    }
}
