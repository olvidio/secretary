<?php

declare(strict_types=1);

namespace src\shared\infrastructure\persistence;

use PDO;
use RuntimeException;

final class ConnectionFactory
{
    public static function env(string $key, ?string $default = null): ?string
    {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return $v;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }

        return $default;
    }

    /**
     * PostgreSQL es el único motor soportado (D9, docs/dev/plan_ampliaciones.md): el
     * PHP del host no tiene `pdo_sqlite`, el stack Docker va con Postgres y el golden
     * master también. Un `DB_DRIVER` distinto de `pgsql` es un error de configuración,
     * no un modo alternativo.
     */
    public static function fromEnv(): PDO
    {
        $driver = self::env('DB_DRIVER', 'pgsql') ?: 'pgsql';
        if ($driver !== 'pgsql') {
            throw new RuntimeException(
                "DB_DRIVER=$driver no soportado: solo se soporta PostgreSQL (DB_DRIVER=pgsql). "
                . 'El soporte de SQLite se retiró (D9, docs/dev/plan_ampliaciones.md).'
            );
        }
        $dsn = self::env('DATABASE_DSN', '') ?: '';
        if ($dsn === '') {
            throw new RuntimeException('DATABASE_DSN es obligatorio con DB_DRIVER=pgsql');
        }
        $pdo = new PDO(
            $dsn,
            self::env('DB_USER'),
            self::env('DB_PASSWORD'),
            self::options()
        );
        $pdo->exec("SET NAMES 'UTF8'");

        return $pdo;
    }

    /** @return array<int, mixed> */
    private static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }
}
