<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class AdminDesvincularCentroUsuario
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(int $identidadId, int $centroId, int $operadorId): void
    {
        if ($identidadId === $operadorId) {
            throw new InvalidArgumentException(_("No puede modificarse a sí mismo"));
        }
        if ($centroId <= 0) {
            throw new InvalidArgumentException(_("Indique el centro"));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Usuario no encontrado"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("No se puede modificar al administrador de plataforma"));
        }
        if ($this->identidades->bajaCentroPendiente($identidadId) !== null) {
            throw new InvalidArgumentException(
                _('La cuenta está en baja programada. Use Reactivar o espere al fin del plazo.')
            );
        }

        $vinculado = false;
        foreach ($this->identidades->centrosDe($identidadId) as $v) {
            if ($v->centroId === $centroId) {
                $vinculado = true;
                break;
            }
        }
        if (!$vinculado) {
            throw new InvalidArgumentException(_("Esa cuenta no está vinculada a ese centro como secretario"));
        }
        if ($this->identidades->contarSecretariosDeCentro($centroId) <= 1) {
            throw new InvalidArgumentException(
                _('Es el único secretario activo de ese centro. Asigne otro secretario antes de quitar el vínculo.')
            );
        }

        $this->identidades->desvincularCentro($identidadId, $centroId);
    }
}
