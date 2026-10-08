<?php

declare(strict_types=1);

namespace src\presupuestos\application;

final class AplicarPrevisionAlPresupuesto
{
    public function __construct(
        private readonly ObtenerPrevisionConsolidada $consolidada,
        private readonly GuardarPresupuesto $presupuesto,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(?string $etiqueta = null): array
    {
        $hoja = $this->consolidada->ejecutar($etiqueta);
        $lineas = [];
        foreach ($hoja['lineas'] as $fila) {
            $lineas[(string) $fila['codigo']] = (string) $fila['total'];
        }
        $this->presupuesto->ejecutar('P', $lineas, (string) ($hoja['etiqueta_presupuesto'] ?? ''));

        return $hoja;
    }
}
