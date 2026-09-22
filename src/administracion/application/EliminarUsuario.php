<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class EliminarUsuario
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly EliminarCuentaPersonal $eliminarPersonal,
        private readonly ProgramarBajaCuentaCentro $programarBajaCentro,
    ) {
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
        if ($this->identidades->esCuentaPersonal($identidadId)) {
            $this->eliminarPersonal->ejecutar($identidadId, true, true);

            return;
        }
        if ($this->identidades->esCuentaSecretarioCentro($identidadId)) {
            $this->programarBajaCentro->ejecutar($identidadId, true);

            return;
        }
        throw new InvalidArgumentException(_('Tipo de cuenta no reconocido; no se puede dar de baja.'));
    }
}
