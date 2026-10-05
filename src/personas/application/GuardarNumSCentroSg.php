<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class GuardarNumSCentroSg
{
    public function __construct(
        private readonly CentroRepository $centros,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    public function ejecutar(int $numS): int
    {
        $centroId = $this->ambito->ejecutar()->centroId;
        $centro = $this->centros->porId($centroId);
        if ($centro === null || !CatalogoPlanesContables::esCentroSg($centro->planContableCodigo)) {
            throw new InvalidArgumentException(_('Esta función es del plan H16s (centro sg)'));
        }
        $numS = max(0, $numS);
        $this->centros->guardarNumS($centroId, $numS);

        return $numS;
    }
}
