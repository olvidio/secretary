<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

/** Quita el mandato de un secretario sobre el centro (desde Configuración / Centro). */
final class DesvincularUsuarioCentro
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly QuedaEscritorCentro $quedaEscritor,
    ) {
    }

    public function ejecutar(int $centroId, int $identidadId, int $operadorId): void
    {
        if ($operadorId <= 0) {
            throw new InvalidArgumentException(_("Sesión no válida"));
        }
        if ($identidadId <= 0) {
            throw new InvalidArgumentException(_("Ese usuario no pertenece a este centro"));
        }
        if ($identidadId === $operadorId) {
            throw new InvalidArgumentException(_("No puede quitarse a sí mismo"));
        }
        if ($centroId <= 0) {
            throw new InvalidArgumentException(_("Centro no encontrado"));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Usuario no encontrado"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("No se puede quitar al administrador de plataforma"));
        }
        if ($this->identidades->bajaCentroPendiente($identidadId) !== null) {
            throw new InvalidArgumentException(
                _('La cuenta está en baja programada. Use Reactivar o espere al fin del plazo.')
            );
        }
        if ($this->identidades->rolEnCentro($identidadId, $centroId) === null) {
            throw new InvalidArgumentException(_("Ese usuario no pertenece a este centro"));
        }

        $this->quedaEscritor->comprobar($centroId, $identidadId);
        $this->identidades->desvincularCentro($identidadId, $centroId);
    }
}
