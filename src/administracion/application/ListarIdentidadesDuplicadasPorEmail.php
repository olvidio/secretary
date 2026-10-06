<?php

declare(strict_types=1);

namespace src\administracion\application;

use src\acceso\domain\contracts\IdentidadRepository;

final class ListarIdentidadesDuplicadasPorEmail
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(): array
    {
        return $this->identidades->listarGruposEmailDuplicado();
    }
}
