<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\shared\domain\contracts\EnviadorCorreo;
use src\shared\infrastructure\persistence\ConnectionFactory;

final class NotificarRegistroUsuario
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly EnviadorCorreo $correo,
    ) {
    }

    public function ejecutar(int $identidadId, string $token): void
    {
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null || $identidad->id === null) {
            throw new InvalidArgumentException(_('Cuenta no encontrada'));
        }
        $base = rtrim(ConnectionFactory::env('APP_URL', 'http://127.0.0.1:8088') ?: 'http://127.0.0.1:8088', '/');
        $enlace = $base . '/confirmar-email?token=' . rawurlencode($token);
        $nombre = $identidad->nombre !== '' ? $identidad->nombre : ($identidad->alias ?? $identidad->email);
        $asunto = _('Bienvenido a Secretario — confirme su correo');
        $cuerpo = sprintf(
            _("Hola %s,\n\nGracias por registrarse en Secretario.\n\nPara activar su cuenta, abra este enlace (válido 48 horas):\n\n%s\n\nSi no ha sido usted, ignore este mensaje.\n"),
            $nombre,
            $enlace,
        );
        $this->correo->enviar($identidad->email, $asunto, $cuerpo);
    }
}
