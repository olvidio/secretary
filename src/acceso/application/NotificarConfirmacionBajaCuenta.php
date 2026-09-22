<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\shared\domain\contracts\EnviadorCorreo;
use src\shared\infrastructure\persistence\ConnectionFactory;

final class NotificarConfirmacionBajaCuenta
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
        $enlace = $base . '/confirmar-baja?token=' . rawurlencode($token);
        $nombre = $identidad->nombre !== '' ? $identidad->nombre : ($identidad->alias ?? $identidad->email);
        $asunto = _('Confirme la baja de su cuenta en Secretario');
        $cuerpo = sprintf(
            _("Hola %s,\n\nHa solicitado dar de baja su cuenta personal en Secretario.\n\nSi fue usted, abra este enlace en las próximas 48 horas para confirmar el borrado definitivo de su cuenta y de los datos de su libro personal (las remesas ya enviadas al centro se conservan en manos del secretario):\n\n%s\n\nSi no ha sido usted, ignore este mensaje: la cuenta seguirá activa.\n"),
            $nombre,
            $enlace,
        );
        $this->correo->enviar($identidad->email, $asunto, $cuerpo);
    }
}
