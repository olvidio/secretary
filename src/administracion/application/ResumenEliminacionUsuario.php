<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class ResumenEliminacionUsuario
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly ResumenEliminacionCuentaPersonal $personal,
        private readonly ResumenBajaCuentaCentro $centro,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $identidadId): array
    {
        if ($this->identidades->esCuentaPersonal($identidadId)) {
            return $this->personal->ejecutar($identidadId);
        }
        if ($this->identidades->esCuentaSecretarioCentro($identidadId)) {
            return $this->centro->ejecutar($identidadId);
        }

        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Usuario no encontrado"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("No se puede eliminar al administrador de plataforma"));
        }

        return [
            'id' => $identidadId,
            'alias' => $identidad->alias,
            'email' => $identidad->email,
            'nombre' => $identidad->nombre,
            'es_personal' => false,
            'es_secretario' => false,
            'es_vacia' => true,
            'puede_borrar' => true,
            'motivo_bloqueo' => null,
            'datos' => null,
            'texto_datos' => _('No tiene libro personal ni está vinculada a ninguna entidad.'),
            'texto_conservacion' => null,
        ];
    }
}
