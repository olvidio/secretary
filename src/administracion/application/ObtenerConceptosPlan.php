<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\plan\domain\contracts\PlanContableRepository;
use src\plan\domain\contracts\PlanConceptoRepository;

final class ObtenerConceptosPlan
{
    public function __construct(
        private readonly PlanContableRepository $planes,
        private readonly PlanConceptoRepository $planConceptos,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(int $planId, ?string $cuenta = null): array
    {
        if ($this->planes->porId($planId) === null) {
            throw new InvalidArgumentException(_("Plan contable no encontrado"));
        }
        $this->planConceptos->sembrarCatalogoSiVacio($planId);
        $out = [];
        foreach ($this->planConceptos->listar($planId, $cuenta) as $c) {
            $out[] = $c->toArray();
        }

        return $out;
    }
}
