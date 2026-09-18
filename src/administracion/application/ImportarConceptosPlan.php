<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\plan\domain\contracts\PlanContableRepository;

final class ImportarConceptosPlan
{
    public function __construct(
        private readonly PlanContableRepository $planes,
        private readonly GuardarConceptosPlan $guardarConceptos,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<array<string, mixed>>
     */
    public function ejecutar(int $planId, array $datos): array
    {
        if ($this->planes->porId($planId) === null) {
            throw new InvalidArgumentException(_("Plan contable no encontrado"));
        }
        $conceptos = $this->extraerConceptos($datos);
        if ($conceptos === []) {
            throw new InvalidArgumentException(_("El fichero no contiene conceptos"));
        }

        return $this->guardarConceptos->ejecutar($planId, ['conceptos' => $conceptos]);
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<array<string, mixed>>
     */
    private function extraerConceptos(array $datos): array
    {
        if (isset($datos['conceptos']) && is_array($datos['conceptos'])) {
            return array_values(array_filter($datos['conceptos'], is_array(...)));
        }
        if (array_is_list($datos)) {
            return array_values(array_filter($datos, is_array(...)));
        }

        return [];
    }
}
