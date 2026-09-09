<?php

declare(strict_types=1);

namespace src\shared\infrastructure\persistence;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Runner de migraciones versionadas (Fase 1, docs/dev/plan_ampliaciones.md, D9).
 *
 * Lee ficheros `NNNN_descripcion.sql` de un directorio, en orden lexicográfico, y
 * aplica los que faltan contra `schema_migrations`. Cada fichero se ejecuta en su
 * propia transacción: si falla, se revierte entero y no se marca como aplicado.
 *
 * Checksum: si una migración ya aplicada cambia de contenido en disco, el runner
 * falla con un mensaje claro en vez de ignorarlo o reaplicarla. No se edita una
 * migración aplicada; se crea una nueva con el cambio.
 *
 * Baseline: si la base ya tiene la tabla `apuntes` (esquema anterior a este runner)
 * y no hay ninguna fila en `schema_migrations`, la migración `0001` se marca como
 * aplicada sin ejecutar su SQL — sus `CREATE TABLE IF NOT EXISTS` no harían nada de
 * todos modos, pero ejecutarla no es necesario ni deseable (evita, por ejemplo,
 * volver a correr sentencias no idempotentes que una migración futura pudiera traer).
 *
 * Bloqueo: usa `pg_advisory_lock` para que dos procesos no migren a la vez sobre la
 * misma base.
 */
final class MigrationRunner
{
    /**
     * Clave arbitraria y estable para el bloqueo de migraciones de esta aplicación.
     * No tiene otro significado que evitar colisión con otros usos de advisory locks.
     */
    public const ADVISORY_LOCK_KEY = 5_284_913;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migracionesDir,
    ) {
    }

    /**
     * Aplica las migraciones pendientes. Devuelve las versiones aplicadas en esta
     * ejecución (lista vacía si no había nada pendiente).
     *
     * @return list<string>
     */
    public function migrar(): array
    {
        $this->asegurarTablaControl();
        $this->pdo->exec('SELECT pg_advisory_lock(' . self::ADVISORY_LOCK_KEY . ')');
        try {
            $this->aplicarBaselineSiProcede();
            $aplicadas = [];
            foreach ($this->pendientes() as $version => $fichero) {
                $this->aplicarUna($version, $fichero);
                $aplicadas[] = $version;
            }

            return $aplicadas;
        } finally {
            $this->pdo->exec('SELECT pg_advisory_unlock(' . self::ADVISORY_LOCK_KEY . ')');
        }
    }

    /**
     * Estado actual: migraciones aplicadas (con fecha) y pendientes. No aplica el
     * baseline (eso solo ocurre en migrar()); una base preexistente sin `schema_migrations`
     * aparecerá con la 0001 como pendiente hasta que se ejecute `db:migrate`.
     * Verifica checksums igualmente, así que puede lanzar la misma excepción que migrar().
     *
     * @return array{aplicadas: list<array{version: string, aplicada_at: string}>, pendientes: list<string>}
     */
    public function estado(): array
    {
        $this->asegurarTablaControl();
        $pendientes = array_keys($this->pendientes());
        $filas = $this->pdo->query('SELECT version, aplicada_at FROM schema_migrations ORDER BY version')->fetchAll();

        return [
            'aplicadas' => array_map(
                static fn (array $f): array => ['version' => (string) $f['version'], 'aplicada_at' => (string) $f['aplicada_at']],
                $filas
            ),
            'pendientes' => $pendientes,
        ];
    }

    private function asegurarTablaControl(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY,
                aplicada_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                checksum TEXT NOT NULL
            )'
        );
    }

    /**
     * Si la base tiene ya `apuntes` (esquema anterior a este runner) y no hay ninguna
     * migración registrada, marca la 0001 como aplicada sin ejecutarla.
     */
    private function aplicarBaselineSiProcede(): void
    {
        if ($this->aplicadas() !== []) {
            return;
        }
        if (!$this->tablaExiste('apuntes')) {
            return;
        }
        $ficheros = $this->ficheros();
        if (!isset($ficheros['0001'])) {
            return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (:v, :c)');
        $stmt->execute([':v' => '0001', ':c' => $this->checksum($ficheros['0001'])]);
    }

    private function tablaExiste(string $tabla): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t)"
        );
        $stmt->execute([':t' => $tabla]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Migraciones pendientes de aplicar, en orden. Verifica de paso el checksum de las
     * ya aplicadas: si una migración aplicada cambió de contenido en disco, lanza.
     *
     * @return array<string, string> version => ruta absoluta
     */
    private function pendientes(): array
    {
        $aplicadas = $this->aplicadas();
        $pendientes = [];
        foreach ($this->ficheros() as $version => $fichero) {
            $checksumEnDisco = $this->checksum($fichero);
            if (array_key_exists($version, $aplicadas)) {
                if ($aplicadas[$version] !== $checksumEnDisco) {
                    throw new RuntimeException(sprintf(
                        'La migración %s ya está aplicada pero su fichero ha cambiado de contenido '
                        . '(checksum distinto). No se edita una migración aplicada: crea una nueva '
                        . 'migración con el cambio que haga falta.',
                        $version
                    ));
                }
                continue;
            }
            $pendientes[$version] = $fichero;
        }

        return $pendientes;
    }

    /** @return array<string, string> version => checksum, según schema_migrations */
    private function aplicadas(): array
    {
        $filas = $this->pdo->query('SELECT version, checksum FROM schema_migrations')->fetchAll();
        $out = [];
        foreach ($filas as $fila) {
            $out[(string) $fila['version']] = (string) $fila['checksum'];
        }

        return $out;
    }

    /**
     * Ficheros de migración del directorio, en orden lexicográfico (= orden de versión
     * con el formato NNNN de 4 dígitos).
     *
     * @return array<string, string> version => ruta absoluta
     */
    private function ficheros(): array
    {
        $rutas = glob(rtrim($this->migracionesDir, '/') . '/*.sql') ?: [];
        sort($rutas, SORT_STRING);
        $out = [];
        foreach ($rutas as $ruta) {
            $nombre = basename($ruta);
            if (preg_match('/^(\d{4})_.+\.sql$/', $nombre, $m) !== 1) {
                throw new RuntimeException(
                    "Nombre de migración inválido: $nombre (se espera NNNN_descripcion.sql, p. ej. 0002_algo.sql)"
                );
            }
            $out[$m[1]] = $ruta;
        }

        return $out;
    }

    private function aplicarUna(string $version, string $fichero): void
    {
        $sql = file_get_contents($fichero);
        if ($sql === false) {
            throw new RuntimeException("No se pudo leer la migración $fichero");
        }
        $checksum = $this->checksum($fichero);
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec($sql);
            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (:v, :c)');
            $stmt->execute([':v' => $version, ':c' => $checksum]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException(
                "La migración $version ($fichero) falló y se ha revertido: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function checksum(string $fichero): string
    {
        $contenido = file_get_contents($fichero);
        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer $fichero");
        }

        return hash('sha256', $contenido);
    }
}
