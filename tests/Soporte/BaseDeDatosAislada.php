<?php

declare(strict_types=1);

namespace Tests\Soporte;

use PDO;
use RuntimeException;

/**
 * Aísla los tests de integración que necesitan una base Postgres real de la base de
 * desarrollo `secretario`. La usan `GoldenMasterTest`, `SchemaInstallerTest` y
 * `MigrationRunnerTest` (Fase 1, docs/dev/plan_ampliaciones.md).
 *
 * Ningún test que use este trait debe tocar `secretario`: `prepararBaseDeTestVacia()`
 * rechaza en tiempo de ejecución que se le pida esa base.
 */
trait BaseDeDatosAislada
{
    private const DB_HOST = '127.0.0.1';
    private const DB_PORT = '5455';
    private const DB_USER = 'secretario';
    private const DB_PASSWORD = 'secretario';

    /** Salta el test si no hay driver PDO pgsql disponible en este PHP. */
    private function saltarSiNoHayPgsql(): void
    {
        if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('No hay driver PDO pgsql disponible.');
        }
    }

    /**
     * Crea (si hace falta) y deja vacía la base de test indicada, con el esquema
     * `public` recién creado. Nunca opera sobre `secretario`.
     */
    private function prepararBaseDeTestVacia(string $dbName = 'secretario_test'): PDO
    {
        if ($dbName === 'secretario') {
            throw new RuntimeException('La base de test no puede ser la de desarrollo.');
        }

        $dsnMantenimiento = sprintf('pgsql:host=%s;port=%s;dbname=postgres', self::DB_HOST, self::DB_PORT);
        $admin = new PDO($dsnMantenimiento, self::DB_USER, self::DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $existe = $admin->query(
            'SELECT 1 FROM pg_database WHERE datname = ' . $admin->quote($dbName)
        )->fetchColumn();
        if ($existe === false) {
            $admin->exec('CREATE DATABASE ' . $dbName);
        }
        $admin = null;

        $dsnTest = sprintf('pgsql:host=%s;port=%s;dbname=%s', self::DB_HOST, self::DB_PORT, $dbName);
        $pdo = new PDO($dsnTest, self::DB_USER, self::DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET NAMES 'UTF8'");
        // Esquema limpio en cada ejecución: sin esto, las columnas IDENTITY seguirían
        // incrementando entre ejecuciones y las pruebas dejarían de ser reproducibles.
        $pdo->exec('DROP SCHEMA public CASCADE');
        $pdo->exec('CREATE SCHEMA public');

        return $pdo;
    }
}
