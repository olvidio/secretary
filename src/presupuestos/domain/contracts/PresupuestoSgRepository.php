<?php

declare(strict_types=1);

namespace src\presupuestos\domain\contracts;

use src\presupuestos\domain\entity\LineaPresupuesto;

interface PresupuestoSgRepository
{
    /** @return list<LineaPresupuesto> */
    public function listar(int $centroId, int $ejercicioId): array;

    public function guardar(int $centroId, int $ejercicioId, LineaPresupuesto $linea): void;

}
