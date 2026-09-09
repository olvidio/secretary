<?php

declare(strict_types=1);

namespace src\acceso\infrastructure\persistence;

use PDO;
use src\acceso\application\CatalogoRutas;
use src\acceso\domain\entity\Identidad;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\shared\infrastructure\persistence\ConnectionFactory;

/** Siembra rutas_acceso e identidad de centro a partir de `usuarios` / APP_USER (D7). */
final class AccesoSeeder
{
    public static function sembrar(PDO $pdo): void
    {
        $existe = $pdo->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'identidades'"
        )->fetchColumn();
        if ($existe === false) {
            return;
        }
        self::sembrarRutas($pdo);
        self::sembrarIdentidadCentro($pdo);
        self::sembrarIdentidadPersona($pdo);
    }

    private static function sembrarRutas(PDO $pdo): void
    {
        $st = $pdo->prepare(
            'INSERT INTO rutas_acceso (clase, metodo_php, ambito)
             VALUES (:c, :m, :a)
             ON CONFLICT (clase, metodo_php) DO UPDATE SET ambito = excluded.ambito'
        );
        foreach (CatalogoRutas::todas() as $fila) {
            $st->execute([
                ':c' => $fila['clase'],
                ':m' => $fila['metodo'],
                ':a' => $fila['ambito'],
            ]);
        }
    }

    private static function sembrarIdentidadCentro(PDO $pdo): void
    {
        $repo = new PdoIdentidadRepository($pdo);
        $alias = strtolower(ConnectionFactory::env('APP_USER', 'scl') ?: 'scl');
        $pass = ConnectionFactory::env('APP_PASSWORD', 'cambiar') ?: 'cambiar';
        $identidad = $repo->porEmailOAlias($alias);
        if ($identidad === null) {
            $hash = null;
            $stUser = $pdo->prepare('SELECT password_hash FROM usuarios WHERE usuario = :u');
            $stUser->execute([':u' => $alias]);
            $row = $stUser->fetch();
            if (is_array($row)) {
                $hash = (string) $row['password_hash'];
            }
            $hash ??= password_hash($pass, PASSWORD_DEFAULT);
            $identidad = $repo->guardar(new Identidad(
                null,
                $alias . '@secretario.local',
                $hash,
                $alias,
                true,
                0,
                null,
                null,
                $alias,
            ));
        }
        if ($identidad->id === null) {
            return;
        }
        if ($repo->centrosDe($identidad->id) !== []) {
            return;
        }
        $centroRepo = new PdoCentroRepository($pdo);
        $codigo = '';
        $cfgExiste = $pdo->query('SELECT 1 FROM configuracion WHERE id = 1')->fetchColumn();
        if ($cfgExiste !== false) {
            $codigo = trim((new PdoConfiguracionRepository($pdo))->get()->centro);
        }
        $centro = $codigo !== '' ? $centroRepo->porCodigo($codigo) : null;
        $centro ??= $centroRepo->listar()[0] ?? null;
        if ($centro?->id !== null) {
            $repo->vincularCentro($identidad->id, $centro->id, 'admin');
        }
    }

    /** Identidad de demostración del nivel 1: alias `yo`, sin TOTP obligatorio. */
    private static function sembrarIdentidadPersona(PDO $pdo): void
    {
        $existePersonas = $pdo->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'personas'"
        )->fetchColumn();
        if ($existePersonas === false) {
            return;
        }
        $st = $pdo->query(
            'SELECT id FROM personas WHERE centro_id IS NOT NULL ORDER BY orden, id LIMIT 1'
        );
        if ($st === false) {
            return;
        }
        $personaId = $st->fetchColumn();
        if ($personaId === false) {
            return;
        }
        $repo = new PdoIdentidadRepository($pdo);
        $pass = ConnectionFactory::env('APP_PASSWORD', 'cambiar') ?: 'cambiar';
        $identidad = $repo->porEmailOAlias('yo');
        if ($identidad === null) {
            $identidad = $repo->guardar(new Identidad(
                null,
                'yo@secretario.local',
                password_hash($pass, PASSWORD_DEFAULT),
                'Yo',
                true,
                0,
                null,
                null,
                'yo',
            ));
        }
        if ($identidad->id === null) {
            return;
        }
        if ($repo->personasDe($identidad->id) !== []) {
            return;
        }
        $repo->vincularPersona($identidad->id, (int) $personaId);
    }
}
