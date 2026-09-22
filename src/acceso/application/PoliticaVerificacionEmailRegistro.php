<?php

declare(strict_types=1);

namespace src\acceso\application;

use DateTimeImmutable;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\services\GeneradorTokenVerificacion;
use src\shared\infrastructure\persistence\ConnectionFactory;

/**
 * Confirmación de correo en el registro público.
 * En desarrollo local (sin SMTP) use REGISTRO_AUTO_CONFIRMA_EMAIL=1 en el .env del stack;
 * en producción déjelo en 0. No se infiere del hecho de ir en Docker.
 */
final class PoliticaVerificacionEmailRegistro
{
    public static function confirmaAlInstante(): bool
    {
        $v = strtolower(trim(ConnectionFactory::env('REGISTRO_AUTO_CONFIRMA_EMAIL', '0') ?? '0'));

        return in_array($v, ['1', 'true', 'yes', 'on', 'si', 'sí'], true);
    }

    /**
     * Marca el correo como verificado o guarda token pendiente.
     *
     * @return array{token: string, enviar_correo: bool}
     */
    public static function prepararTrasAlta(IdentidadRepository $identidades, int $identidadId): array
    {
        if (self::confirmaAlInstante()) {
            $identidades->marcarEmailVerificado($identidadId, new DateTimeImmutable());

            return ['token' => '', 'enviar_correo' => false];
        }
        $token = GeneradorTokenVerificacion::generar();
        $identidades->guardarVerificacionEmail(
            $identidadId,
            $token,
            (new DateTimeImmutable())->modify('+48 hours'),
        );

        return ['token' => $token, 'enviar_correo' => true];
    }
}
