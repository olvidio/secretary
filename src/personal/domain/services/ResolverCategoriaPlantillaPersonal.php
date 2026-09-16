<?php

declare(strict_types=1);

namespace src\personal\domain\services;

use InvalidArgumentException;
use src\ambito\domain\contracts\CuentaRepository;
use src\ambito\domain\entity\Cuenta;
use src\apuntes\domain\contracts\PlantillaApunteRepository;
use src\conceptos\domain\services\CatalogoConceptos;

/** Primera línea de gasto en P de la plantilla → categoría imputable del libro X. */
final class ResolverCategoriaPlantillaPersonal
{
    public function __construct(
        private readonly PlantillaApunteRepository $plantillas,
        private readonly CuentaRepository $cuentas,
    ) {
    }

    public function ejecutar(int $centroId, int $personaId, int $plantillaId): Cuenta
    {
        $plantilla = $this->plantillas->porId($centroId, $plantillaId);
        if ($plantilla === null || !$plantilla->activa || $plantilla->id === null) {
            throw new InvalidArgumentException('Plantilla no encontrada');
        }
        $maestro = null;
        foreach ($plantilla->lineas as $linea) {
            if (strtoupper($linea->cuenta) !== 'P') {
                continue;
            }
            foreach (CatalogoConceptos::todos() as $concepto) {
                if (
                    $concepto['cuenta'] === 'P'
                    && $concepto['codigo'] === $linea->conceptoCodigo
                    && $concepto['naturaleza'] === 'gasto'
                ) {
                    $maestro = $concepto['codigo'];
                    break 2;
                }
            }
        }
        if ($maestro === null) {
            throw new InvalidArgumentException('La plantilla no define un gasto en P');
        }
        if (!CatalogoMaestroPersonal::existe($maestro)) {
            throw new InvalidArgumentException('El gasto de la plantilla no está en el plan personal');
        }
        $cuenta = $this->cuentas->buscar($centroId, $personaId, 'X', $maestro);
        if ($cuenta === null || $cuenta->id === null || !in_array($cuenta->tipo, ['ingreso', 'gasto'], true)) {
            throw new InvalidArgumentException('No hay categoría personal para ' . $maestro);
        }

        return $cuenta;
    }
}
