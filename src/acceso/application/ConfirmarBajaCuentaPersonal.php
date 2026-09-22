<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\administracion\application\EliminarCuentaPersonal;

final class ConfirmarBajaCuentaPersonal
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly EliminarCuentaPersonal $eliminar,
    ) {
    }

    public function ejecutar(string $token, ?DateTimeImmutable $ahora = null): void
    {
        $ahora ??= new DateTimeImmutable();
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException(_('Enlace de confirmación no válido'));
        }
        $datos = $this->identidades->porTokenBajaCuenta($token);
        if ($datos === null) {
            throw new InvalidArgumentException(_('Enlace de confirmación no válido o ya utilizado'));
        }
        if ($datos['expira'] < $ahora) {
            $this->identidades->limpiarTokenBajaCuenta($datos['identidad_id']);
            throw new InvalidArgumentException(
                _('El enlace ha caducado. Vuelva a solicitar la baja desde su cuenta.')
            );
        }

        $this->identidades->limpiarTokenBajaCuenta($datos['identidad_id']);
        $this->eliminar->ejecutar($datos['identidad_id'], true, false);
    }
}
