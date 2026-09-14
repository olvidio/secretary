<?php

declare(strict_types=1);

namespace src\shared\infrastructure\persistence;

/** Ruta del directorio de volcados (escribible por PHP-FPM / CLI). */
final class RutasCopiasSeguridad
{
    public static function directorio(): string
    {
        $custom = ConnectionFactory::env('BACKUP_DIR');
        if ($custom !== null && $custom !== '') {
            return rtrim($custom, '/');
        }

        // Mismo criterio que SchemaInstaller (un nivel más profundo que Kernel).
        return dirname(__DIR__, 4) . '/var/backups';
    }
}
