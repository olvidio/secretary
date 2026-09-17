<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\services\GeneradorTokenVerificacion;

final class ReenviarCorreoVerificacion
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly NotificarRegistroUsuario $notificar,
    ) {
    }

    public function ejecutar(string $email, ?DateTimeImmutable $ahora = null): void
    {
        $ahora ??= new DateTimeImmutable();
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(_('El correo no es válido'));
        }
        $identidad = $this->identidades->porEmailOAlias($email);
        if ($identidad === null || $identidad->id === null) {
            return;
        }
        if ($this->identidades->emailVerificado($identidad->id)) {
            return;
        }
        $token = GeneradorTokenVerificacion::generar();
        $this->identidades->guardarVerificacionEmail(
            $identidad->id,
            $token,
            $ahora->modify('+48 hours'),
        );
        $this->notificar->ejecutar($identidad->id, $token);
    }
}
