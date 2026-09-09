<?php

declare(strict_types=1);

namespace src\apuntes\application;

use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\PlantillaApunteRepository;

final class ListarPlantillasApunte
{
    public function __construct(
        private readonly PlantillaApunteRepository $plantillas,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ejecutar(string $cuenta): array
    {
        $cuenta = strtoupper(trim($cuenta));
        if (!in_array($cuenta, ['P', 'G'], true)) {
            return [];
        }
        $ctx = $this->ambito->ejecutar();
        $out = [];
        foreach ($this->plantillas->listar($ctx->centroId, $cuenta) as $p) {
            if (!$this->aplicaALibro($p, $cuenta)) {
                continue;
            }
            $out[] = $p->toArray();
        }

        return $out;
    }

    private function aplicaALibro(\src\apuntes\domain\entity\PlantillaApunte $p, string $cuenta): bool
    {
        if (strtoupper($p->cuenta) === strtoupper($cuenta)) {
            return true;
        }
        foreach ($p->lineas as $linea) {
            if (strtoupper($linea->cuenta) === strtoupper($cuenta)) {
                return true;
            }
        }

        return false;
    }
}
