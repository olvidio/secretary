<?php

declare(strict_types=1);

namespace src\personal\infrastructure\persistence;

use src\shared\infrastructure\persistence\ConnectionFactory;

/** Directorio de copias del libro personal (JSON por persona). */
final class RutasCopiasPersonal
{
    public static function directorio(): string
    {
        $custom = ConnectionFactory::env('BACKUP_DIR');
        $base = $custom !== null && $custom !== ''
            ? rtrim($custom, '/')
            : dirname(__DIR__, 4) . '/var/backups';

        return $base . '/personal';
    }
}
