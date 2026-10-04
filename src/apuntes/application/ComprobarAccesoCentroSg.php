<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class ComprobarAccesoCentroSg
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
    ) {
    }

    public function ejecutar(): void
    {
        $ctx = $this->ambito->ejecutar();
        $centro = $this->centros->porId($ctx->centroId);
        if ($centro === null || !CatalogoPlanesContables::esCentroSg($centro->planContableCodigo)) {
            throw new InvalidArgumentException(_("Esta función es del plan H16s (centro sg)"));
        }
    }
}
