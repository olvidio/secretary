<?php

declare(strict_types=1);

namespace src\configuracion\application;

use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class ObtenerConfiguracion
{
    public function __construct(
        private readonly ConfiguracionRepository $repo,
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(): array
    {
        $out = $this->repo->get()->toArray();
        try {
            $centro = $this->centros->porId($this->ambito->ejecutar()->centroId);
            if ($centro !== null) {
                $out['tipo'] = $centro->tipo;
                $out['tipo_cierre'] = $centro->tipoCierre;
                $out['plan_contable'] = $centro->planContableCodigo;
                $out['es_club'] = CatalogoPlanesContables::esClub($centro->planContableCodigo);
                $out['es_centro_sg'] = CatalogoPlanesContables::esCentroSg($centro->planContableCodigo);
                if ($out['es_club'] || $out['es_centro_sg']) {
                    $out['centro'] = $centro->codigo;
                }
            }
        } catch (\Throwable) {
            $out['tipo'] = 'n';
        }

        return $out;
    }
}
