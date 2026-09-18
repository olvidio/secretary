<?php

declare(strict_types=1);

namespace src\conceptos\application;

use src\ambito\application\ResolverAmbitoActual;
use src\conceptos\domain\contracts\ConceptoRepository;

final class ListarConceptos
{
    public function __construct(
        private readonly ConceptoRepository $repo,
        private readonly ResolverConceptosCentro $resolver,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(?string $cuenta = null): array
    {
        try {
            return $this->resolver->listar($this->ambito->ejecutar()->centroId, $cuenta);
        } catch (\Throwable) {
            $out = [];
            foreach ($this->repo->listar($cuenta) as $c) {
                $out[] = $c->toArray();
            }

            return $out;
        }
    }
}
