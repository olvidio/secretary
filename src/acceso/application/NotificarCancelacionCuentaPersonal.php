<?php

declare(strict_types=1);

namespace src\acceso\application;

use src\shared\domain\contracts\EnviadorCorreo;

final class NotificarCancelacionCuentaPersonal
{
    public function __construct(private readonly EnviadorCorreo $correo)
    {
    }

    public function ejecutar(string $email, string $nombre): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }
        $nombre = trim($nombre) !== '' ? trim($nombre) : $email;
        $asunto = _('Su cuenta en Secretario ha sido cancelada');
        $cuerpo = sprintf(
            _("Hola %s,\n\nLe informamos de que su cuenta personal en Secretario ha sido dada de baja por el administrador de la plataforma.\n\nYa no podrá entrar con este usuario. Los datos de contabilidad del centro que ya se hubieran enviado (remesas aceptadas o en curso) permanecen en manos del secretario del centro.\n\nSi cree que se trata de un error, contacte con quien gestiona su comunidad o con el administrador del servicio.\n"),
            $nombre,
        );
        $this->correo->enviar($email, $asunto, $cuerpo);
    }
}
