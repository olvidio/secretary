<?php

declare(strict_types=1);

namespace src\ambito\application;

use src\ambito\domain\contracts\CuentaFisicaRepository;

final class ListarCuentasFisicas
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CuentaFisicaRepository $fisicas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(bool $soloActivas = false): array
    {
        $contexto = $this->ambito->ejecutar();
        $lista = $soloActivas
            ? $this->fisicas->listarActivasDeCentro($contexto->centroId)
            : $this->fisicas->listarDeCentro($contexto->centroId);

        return array_map(static fn ($f) => $f->toArray(), $lista);
    }
}
