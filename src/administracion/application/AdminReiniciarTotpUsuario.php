<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

/** Permite al admin de plataforma invalidar 2FA para que el usuario configure un QR nuevo. */
final class AdminReiniciarTotpUsuario
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(int $identidadId, int $operadorId, bool $confirmar): void
    {
        if (!$confirmar) {
            throw new InvalidArgumentException(_("Hay que confirmar el reinicio del segundo factor"));
        }
        if ($identidadId === $operadorId) {
            throw new InvalidArgumentException(
                _("No puede reiniciar su propio segundo factor desde aquí; use otro administrador o la base de datos.")
            );
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Usuario no encontrado"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("No se puede reiniciar el segundo factor del administrador de plataforma"));
        }
        $this->identidades->reiniciarTotp($identidadId);
    }
}
