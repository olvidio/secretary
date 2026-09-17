<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class ConfirmarEmailRegistro
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(string $token, ?DateTimeImmutable $ahora = null): void
    {
        $ahora ??= new DateTimeImmutable();
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException(_('Enlace de confirmación no válido'));
        }
        $datos = $this->identidades->porTokenVerificacionEmail($token);
        if ($datos === null) {
            throw new InvalidArgumentException(_('Enlace de confirmación no válido o ya utilizado'));
        }
        if ($datos['expira'] < $ahora) {
            throw new InvalidArgumentException(_('El enlace ha caducado. Regístrese de nuevo o pida otro correo.'));
        }
        if ($this->identidades->emailVerificado($datos['identidad_id'])) {
            return;
        }
        $this->identidades->confirmarEmail($datos['identidad_id'], $ahora);
    }
}
