<?php

declare(strict_types=1);

namespace src\plan\domain\contracts;

use src\conceptos\domain\entity\Concepto;

interface PlanConceptoRepository
{
    /** @return list<Concepto> */
    public function listar(int $planId, ?string $cuenta = null): array;

    /**
     * @param list<array{codigo:string,cuenta:string,nombre:string,descripcion:string,naturaleza:string,orden:int}> $conceptos
     */
    public function reemplazar(int $planId, array $conceptos): void;

    public function copiarDesdePlan(int $origenId, int $destinoId): void;

    public function planIdDeCentro(int $centroId): ?int;
}
