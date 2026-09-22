<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use src\acceso\domain\contracts\IdentidadRepository;
use src\shared\domain\contracts\EnviadorCorreo;

final class NotificarInicioBajaCentro
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly EnviadorCorreo $correo,
    ) {
    }

    public function ejecutar(int $identidadId, DateTimeImmutable $ejecutarAt): void
    {
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            return;
        }
        $nombre = $identidad->nombre !== '' ? $identidad->nombre : ($identidad->alias ?? $identidad->email);
        $fecha = $ejecutarAt->format('Y-m-d');
        $asunto = _('Baja de su cuenta de secretario en Secretario');
        $cuerpo = sprintf(
            _("Hola %s,\n\nSe ha programado la baja de su acceso como secretario de centro en Secretario.\n\nYa no puede entrar. Los datos contables del centro no se borran.\n\nSi nadie reactiva la cuenta antes del %s, se eliminarán sus credenciales de acceso.\n\nSi cree que es un error, contacte con el administrador de la plataforma.\n"),
            $nombre,
            $fecha,
        );
        $this->correo->enviar($identidad->email, $asunto, $cuerpo);
    }
}
