<?php

declare(strict_types=1);

namespace src\administracion\application;

use src\plan\domain\contracts\PlanContableRepository;

final class EliminarPlanContable
{
    public function __construct(private readonly PlanContableRepository $planes)
    {
    }

    public function ejecutar(int $id): void
    {
        $this->planes->borrar($id);
    }
}
