<?php

declare(strict_types=1);

namespace src\presupuestos\domain\contracts;

use src\presupuestos\domain\entity\LineaPresupuesto;

interface PresupuestoSgRepository
{
    /** @return list<LineaPresupuesto> */
    public function listar(int $centroId): array;

    public function guardar(int $centroId, LineaPresupuesto $linea): void;

    public function numS(int $centroId): int;

    public function guardarNumS(int $centroId, int $numS): void;
}
