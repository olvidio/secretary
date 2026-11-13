<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\value_objects\RolCentro;
use src\administracion\application\EliminarCentro;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\services\TipoEntidad;

/**
 * Quien administra una entidad la elimina con todos sus datos.
 * Solo el rol administrador; hay que escribir la sigla para confirmar.
 */
final class EliminarEntidadPropia
{
    public function __construct(
        private readonly EliminarCentro $eliminarCentro,
        private readonly CentroRepository $centros,
        private readonly IdentidadRepository $identidades,
    ) {
    }

    public function ejecutar(int $identidadId, int $centroId, string $siglaConfirmacion): void
    {
        $centro = $this->centros->porId($centroId);
        // `operador` es el nombre antiguo del administrador; `consulta` no elimina.
        $rol = $this->identidades->rolEnCentro($identidadId, $centroId);
        // Mismo mensaje si no existe o si no es suya: no se revela qué entidades hay.
        if (
            $centro === null
            || $centro->tipo === TipoEntidad::PERSONAL
            || $rol === null
            || !RolCentro::puedeEscribir($rol)
        ) {
            throw new InvalidArgumentException(_("Solo quien administra la entidad puede eliminarla"));
        }
        if (mb_strtolower(trim($siglaConfirmacion)) !== mb_strtolower(trim($centro->codigo))) {
            throw new InvalidArgumentException(_("La sigla escrita no coincide con la de la entidad"));
        }

        $this->eliminarCentro->ejecutar($centroId, true);
    }
}
