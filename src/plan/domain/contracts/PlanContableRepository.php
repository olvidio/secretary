<?php

declare(strict_types=1);

namespace src\plan\domain\contracts;

interface PlanContableRepository
{
    public function idPorCodigo(string $codigo): ?int;

    public function codigoPorCentro(int $centroId): string;
}
