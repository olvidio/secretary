<?php

declare(strict_types=1);

namespace src\remesas\application;

use src\personal\application\ResolverPersonaActual;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\SolicitudDetalle;

final class ListarSolicitudesPersonales
{
    public function __construct(
        private readonly PersonasRemesaDeIdentidad $personasRemesa,
        private readonly RemesaRepository $remesas,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ejecutar(): array
    {
        return array_map(
            static fn (SolicitudDetalle $s) => $s->toArray(),
            $this->remesas->solicitudesPendientesDePersonas($this->personasRemesa->ejecutar()),
        );
    }
}
