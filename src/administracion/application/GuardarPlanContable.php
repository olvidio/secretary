<?php

declare(strict_types=1);

namespace src\administracion\application;

use src\plan\domain\contracts\PlanContableRepository;
use src\plan\domain\contracts\PlanConceptoRepository;
use src\plan\domain\services\CatalogoPlanesContables;
final class GuardarPlanContable
{
    public function __construct(
        private readonly PlanContableRepository $planes,
        private readonly PlanConceptoRepository $planConceptos,
    ) {
    }

    /** @param array<string, mixed> $datos
     * @return array{id:int, codigo:string, nombre:string}
     */
    public function ejecutar(array $datos): array
    {
        $id = isset($datos['id']) && $datos['id'] !== '' ? (int) $datos['id'] : null;
        $plan = $this->planes->guardar(
            $id,
            (string) ($datos['codigo'] ?? ''),
            (string) ($datos['nombre'] ?? ''),
        );
        if ($id === null) {
            $origenId = $this->planes->idPorCodigo(CatalogoPlanesContables::H16N);
            if ($origenId !== null) {
                $this->planConceptos->copiarDesdePlan($origenId, $plan['id']);
            }
        }

        return $plan;
    }
}
