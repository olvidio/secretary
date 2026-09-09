<?php

declare(strict_types=1);

namespace src\shared\infrastructure\persistence;

use PDO;
use src\acceso\infrastructure\persistence\AccesoSeeder;
use src\apuntes\infrastructure\persistence\PlantillaApunteSeeder;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\conceptos\domain\services\CatalogoConceptos;
use src\personal\infrastructure\persistence\Nivel1Seeder;

/**
 * `db:install` = `db:migrate` + semillas (Fase 1, docs/dev/plan_ampliaciones.md).
 * El esquema ya no se ejecuta aquí directamente: sale de `migraciones/` a través de
 * `MigrationRunner`. Ver `docs/dev/migraciones.md`.
 */
final class SchemaInstaller
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function install(): void
    {
        (new MigrationRunner($this->pdo, self::migracionesDir()))->migrar();
        $this->seedConceptos();
        $this->seedUsuario();
        $this->seedConfig();
        // AmbitoSeeder (Fase 2, D2/D3/D10/D11) se invoca aquí, DESPUÉS de seedConfig():
        // necesita leer la fila de `configuracion` (centro, año, modo, fechas) para saber
        // qué centro/ejercicio crear, y en una instalación desde cero esa fila no existe
        // hasta este punto. Es el mismo motivo por el que `db:migrate` (bin/console.php),
        // que NO pasa por aquí, tiene que invocarlo también por su cuenta.
        AmbitoSeeder::sembrar($this->pdo);
        AccesoSeeder::sembrar($this->pdo);
        PlantillaApunteSeeder::sembrar($this->pdo);
        Nivel1Seeder::sembrar($this->pdo);
    }

    private static function migracionesDir(): string
    {
        return dirname(__DIR__, 4) . '/migraciones';
    }

    private function seedConceptos(): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO conceptos (codigo, cuenta, nombre, descripcion, naturaleza, orden)
             VALUES (:codigo, :cuenta, :nombre, :descripcion, :naturaleza, :orden)
             ON CONFLICT (codigo, cuenta) DO UPDATE SET
                nombre = excluded.nombre,
                descripcion = excluded.descripcion,
                naturaleza = excluded.naturaleza,
                orden = excluded.orden'
        );
        foreach (CatalogoConceptos::todos() as $c) {
            $st->execute([
                ':codigo' => $c['codigo'],
                ':cuenta' => $c['cuenta'],
                ':nombre' => $c['nombre'],
                ':descripcion' => $c['descripcion'],
                ':naturaleza' => $c['naturaleza'],
                ':orden' => $c['orden'],
            ]);
        }
    }

    private function seedUsuario(): void
    {
        $user = ConnectionFactory::env('APP_USER', 'scl') ?: 'scl';
        $pass = ConnectionFactory::env('APP_PASSWORD', 'cambiar') ?: 'cambiar';
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $exists = $this->pdo->prepare('SELECT id FROM usuarios WHERE usuario = :u');
        $exists->execute([':u' => $user]);
        if ($exists->fetch() === false) {
            $ins = $this->pdo->prepare('INSERT INTO usuarios (usuario, password_hash) VALUES (:u, :h)');
            $ins->execute([':u' => $user, ':h' => $hash]);
        }
    }

    private function seedConfig(): void
    {
        $exists = $this->pdo->query('SELECT id FROM configuracion WHERE id = 1')->fetch();
        if ($exists !== false) {
            return;
        }
        $year = (int) date('Y');
        $st = $this->pdo->prepare(
            'INSERT INTO configuracion (id, centro, anio, modo_ejercicio, fecha_inicio, fecha_cierre, tipo_cierre, version, updated_at)
             VALUES (1, :centro, :anio, :modo, :ini, :cie, :tipo, :ver, :upd)'
        );
        $st->execute([
            ':centro' => 'Centro',
            ':anio' => $year,
            ':modo' => 'Año',
            ':ini' => sprintf('%d-01-01', $year),
            ':cie' => sprintf('%d-01-31', $year),
            ':tipo' => 'vivienda',
            ':ver' => '8',
            ':upd' => date('c'),
        ]);
    }
}
