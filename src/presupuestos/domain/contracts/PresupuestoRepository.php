<?php

declare(strict_types=1);

namespace src\presupuestos\domain\contracts;

use src\presupuestos\domain\entity\LineaPresupuesto;

interface PresupuestoRepository
{
    /** @return list<LineaPresupuesto> */
    public function listar(string $cuenta): array;

    public function guardar(LineaPresupuesto $linea): void;

    public function previsto(string $cuenta, string $concepto): string;

    public function borrarCuenta(string $cuenta): void;
}
