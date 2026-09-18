<?php

declare(strict_types=1);

namespace src\acceso\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\shared\domain\contracts\EnviadorCorreo;
use src\shared\infrastructure\persistence\ConnectionFactory;

final class NotificarRegistroUsuario
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly EnviadorCorreo $correo,
        private readonly CatalogoDocumentosLegales $documentos,
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
        $cond = $this->documentos->vigente('condiciones');
        $priv = $this->documentos->vigente('privacidad');
        $urlCond = $base . '/condiciones';
        $urlPriv = $base . '/privacidad';
        $cuerpo = sprintf(
            _("Hola %s,\n\nGracias por registrarse en Secretario. El servicio es gratuito.\n\nAl abrir el enlace siguiente confirma que este correo es suyo y que acepta las Condiciones de uso (%s) y la Política de privacidad (%s) que le mostramos al registrarse:\n%s\n%s\n\nEl enlace caduca en 48 horas:\n\n%s\n\nResumen: el programa se ofrece en su estado actual; no se garantiza la ausencia de fallos ni la conservación de los datos — haga copias. La ley no permite excluir dolo ni culpa grave. Usamos sus datos para prestar el servicio, seguridad y obligaciones legales; no los vendemos. Quien da de alta nombres en un centro es responsable de esos datos.\n\nSi no ha sido usted, ignore este mensaje. Su cuenta no se activará.\n"),
            $nombre,
            $cond->version,
            $priv->version,
            $urlCond,
            $urlPriv,
            $enlace,
        );
        $this->correo->enviar($identidad->email, $asunto, $cuerpo);
    }
}
