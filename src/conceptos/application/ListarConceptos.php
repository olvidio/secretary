<?php

declare(strict_types=1);

namespace src\conceptos\application;

use src\conceptos\domain\contracts\ConceptoRepository;

final class ListarConceptos
{
    public function __construct(private readonly ConceptoRepository $repo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(?string $cuenta = null): array
    {
        $out = [];
        foreach ($this->repo->listar($cuenta) as $c) {
            $out[] = $c->toArray();
        }

        return $out;
    }
}
