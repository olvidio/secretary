<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\plan\domain\contracts\PlanContableRepository;

final class ExportarConceptosPlan
{
    public function __construct(
        private readonly PlanContableRepository $planes,
        private readonly ObtenerConceptosPlan $obtenerConceptos,
    ) {
    }

    /** @return array{plan: array{id:int,codigo:string,nombre:string}, conceptos: list<array<string, mixed>>} */
    public function ejecutar(int $planId): array
    {
        $plan = $this->planes->porId($planId);
        if ($plan === null) {
            throw new InvalidArgumentException(_("Plan contable no encontrado"));
        }

        return [
            'plan' => $plan,
            'conceptos' => $this->obtenerConceptos->ejecutar($planId),
        ];
    }
}
