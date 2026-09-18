<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class EliminarUsuario
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(int $identidadId, int $operadorId, bool $confirmar): void
    {
        if (!$confirmar) {
            throw new InvalidArgumentException(_("Hay que confirmar el borrado del usuario"));
        }
        if ($identidadId === $operadorId) {
            throw new InvalidArgumentException(_("No puede eliminarse a sí mismo"));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Usuario no encontrado"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("No se puede eliminar al administrador de plataforma"));
        }
        $this->identidades->eliminar($identidadId);
    }
}
