<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\shared\domain\contracts\EnviadorCorreo;
use src\shared\infrastructure\persistence\ConnectionFactory;

final class NotificarCambioEmailUsuario
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
        $destino = $this->identidades->emailPendienteDe($identidadId);
        if ($destino === null) {
            throw new InvalidArgumentException(_('No hay cambio de correo pendiente'));
        }
        $base = rtrim(ConnectionFactory::env('APP_URL', 'http://127.0.0.1:8088') ?: 'http://127.0.0.1:8088', '/');
        $enlace = $base . '/confirmar-email?token=' . rawurlencode($token);
        $nombre = $identidad->nombre !== '' ? $identidad->nombre : ($identidad->alias ?? $identidad->email);
        $asunto = _('Confirme su nuevo correo en Secretario');
        $cuerpo = sprintf(
            _("Hola %s,\n\nHa solicitado cambiar el correo de su cuenta en Secretario.\n\nAbra este enlace para confirmar que %s es suyo (caduca en 48 horas):\n\n%s\n\nHasta entonces seguirá entrando con su correo actual.\n\nSi no ha sido usted, ignore este mensaje.\n"),
            $nombre,
            $destino,
            $enlace,
        );
        $this->correo->enviar($destino, $asunto, $cuerpo);
    }
}
