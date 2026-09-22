<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\services\GeneradorTokenVerificacion;

final class SolicitarBajaCuentaPersonal
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly NotificarConfirmacionBajaCuenta $notificar,
    ) {
    }

    public function ejecutar(int $identidadId, bool $confirmar): void
    {
        if (!$confirmar) {
            throw new InvalidArgumentException(_('Hay que confirmar la solicitud de baja'));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null) {
            throw new InvalidArgumentException(_('Sesión caducada'));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_('Esta cuenta no puede darse de baja desde aquí'));
        }
        if (!$this->identidades->esCuentaPersonal($identidadId)) {
            throw new InvalidArgumentException(
                _('Solo las cuentas personales pueden darse de baja desde aquí. Las cuentas de secretario de centro se gestionan aparte.')
            );
        }
        if (!$this->identidades->emailVerificado($identidadId)) {
            throw new InvalidArgumentException(
                _('Confirme primero su correo desde el enlace que recibió al registrarse.')
            );
        }

        $token = GeneradorTokenVerificacion::generar();
        $expira = (new DateTimeImmutable())->modify('+48 hours');
        $this->identidades->guardarTokenBajaCuenta($identidadId, $token, $expira);
        $this->notificar->ejecutar($identidadId, $token);
    }
}
