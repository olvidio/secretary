<?php

declare(strict_types=1);

namespace src\personas\application;

use src\personas\domain\contracts\PersonaRepository;

final class ListarPersonas
{
    public function __construct(private readonly PersonaRepository $repo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(): array
    {
        $out = [];
        foreach ($this->repo->listar() as $p) {
            $out[] = $p->toArray();
        }

        return $out;
    }
}
