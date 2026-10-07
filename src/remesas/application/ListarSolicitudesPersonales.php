<?php

declare(strict_types=1);

namespace src\remesas\application;

use src\personal\application\ResolverPersonaActual;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\SolicitudDetalle;
use src\remesas\domain\services\AgregadorRemesaPersonal;

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
        return array_map(static function (SolicitudDetalle $s): array {
            $fila = $s->toArray();
            $fila['nombre'] = AgregadorRemesaPersonal::nombreMaestro($s->codigoMaestro);

            return $fila;
        }, $this->remesas->solicitudesPendientesDePersonas($this->personasRemesa->ejecutar()));
    }
}
