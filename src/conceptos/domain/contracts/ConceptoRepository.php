<?php

declare(strict_types=1);

namespace src\conceptos\domain\contracts;

use src\conceptos\domain\entity\Concepto;

interface ConceptoRepository
{
    /** @return list<Concepto> */
    public function listar(?string $cuenta = null): array;

    public function buscar(string $cuenta, string $codigo): ?Concepto;

    public function guardar(Concepto $concepto): void;
}
