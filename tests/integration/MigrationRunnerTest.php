<?php

declare(strict_types=1);

namespace Tests\integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use src\shared\infrastructure\persistence\MigrationRunner;
use Tests\Soporte\BaseDeDatosAislada;

/**
 * Tests del runner de migraciones (Fase 1, docs/dev/plan_ampliaciones.md), sobre
 * `secretario_test`, nunca sobre `secretario`. Usa directorios de migraciones
 * sintéticos (no `migraciones/` de producción) para poder mutar contenido y probar
 * el checksum sin tocar la migración real.
 */
final class MigrationRunnerTest extends TestCase
{
    use BaseDeDatosAislada;

    /** @var list<string> directorios temporales a limpiar en tearDown */
    private array $dirsTemporales = [];

    protected function tearDown(): void
    {
        foreach ($this->dirsTemporales as $dir) {
            $ficheros = glob($dir . '/*.sql') ?: [];
            foreach ($ficheros as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        $this->dirsTemporales = [];
    }

    public function testAplicarDesdeCeroCreaLaTablaYRegistraLaVersion(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        $dir = $this->crearDirMigraciones([
            '0001_cosas.sql' => 'CREATE TABLE cosas (id serial primary key, nombre text not null);',
        ]);

        $runner = new MigrationRunner($pdo, $dir);
        $aplicadas = $runner->migrar();

        self::assertSame(['0001'], $aplicadas);
        $existe = (bool) $pdo->query(
            "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'cosas')"
        )->fetchColumn();
        self::assertTrue($existe);

        $fila = $pdo->query('SELECT version, checksum FROM schema_migrations')->fetch();
        self::assertSame('0001', $fila['version']);
        self::assertSame(hash('sha256', 'CREATE TABLE cosas (id serial primary key, nombre text not null);'), $fila['checksum']);
    }

    public function testAplicarDosVecesEsIdempotente(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        $dir = $this->crearDirMigraciones([
            '0001_cosas.sql' => 'CREATE TABLE cosas (id serial primary key);',
        ]);
        $runner = new MigrationRunner($pdo, $dir);

        $primera = $runner->migrar();
        $segunda = $runner->migrar();

        self::assertSame(['0001'], $primera);
        self::assertSame([], $segunda);
        $n = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        self::assertSame(1, $n);
    }

    public function testChecksumAlteradoFallaEnVezDeReaplicar(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        $dir = $this->crearDirMigraciones([
            '0001_cosas.sql' => 'CREATE TABLE cosas (id serial primary key);',
        ]);
        $runner = new MigrationRunner($pdo, $dir);
        $runner->migrar();

        // Se edita el fichero ya aplicado: el runner debe negarse a continuar.
        file_put_contents($dir . '/0001_cosas.sql', 'CREATE TABLE cosas (id serial primary key, extra text);');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/checksum/i');
        $runner->migrar();
    }

    public function testChecksumAlteradoSeDetectaTambienEnEstado(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        $dir = $this->crearDirMigraciones([
            '0001_cosas.sql' => 'CREATE TABLE cosas (id serial primary key);',
        ]);
        $runner = new MigrationRunner($pdo, $dir);
        $runner->migrar();
        file_put_contents($dir . '/0001_cosas.sql', 'CREATE TABLE cosas (id serial primary key, extra text);');

        $this->expectException(RuntimeException::class);
        $runner->estado();
    }

    public function testBaselineMarcaLaPrimeraMigracionSinEjecutarlaSiYaExisteApuntes(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        // Simula una base preexistente al runner: la tabla `apuntes` ya existe (como en
        // `secretario` hoy) y no hay ninguna fila en `schema_migrations`.
        $pdo->exec('CREATE TABLE apuntes (id serial primary key)');

        // Contenido deliberadamente NO idempotente: si el runner lo ejecutara de verdad
        // (en vez de marcar el baseline), Postgres fallaría por "ya existe" y el test
        // detectaría el error.
        $dir = $this->crearDirMigraciones([
            '0001_inicial.sql' => 'CREATE TABLE apuntes (id serial primary key);',
        ]);
        $runner = new MigrationRunner($pdo, $dir);

        $aplicadas = $runner->migrar();

        self::assertSame([], $aplicadas, 'El baseline no cuenta como "aplicada en esta ejecución"');
        $fila = $pdo->query('SELECT version FROM schema_migrations')->fetch();
        self::assertSame('0001', $fila['version']);

        // Segunda pasada: sigue sin haber nada pendiente y sigue sin fallar.
        self::assertSame([], $runner->migrar());
    }

    public function testEstadoListaAplicadasYPendientes(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();
        $dir = $this->crearDirMigraciones([
            '0001_uno.sql' => 'CREATE TABLE uno (id serial primary key);',
        ]);
        $runner = new MigrationRunner($pdo, $dir);
        $runner->migrar();

        file_put_contents($dir . '/0002_dos.sql', 'CREATE TABLE dos (id serial primary key);');
        $estado = $runner->estado();

        self::assertCount(1, $estado['aplicadas']);
        self::assertSame('0001', $estado['aplicadas'][0]['version']);
        self::assertNotSame('', $estado['aplicadas'][0]['aplicada_at']);
        self::assertSame(['0002'], $estado['pendientes']);
    }

    /** @param array<string, string> $ficheros nombre => contenido SQL */
    private function crearDirMigraciones(array $ficheros): string
    {
        $dir = sys_get_temp_dir() . '/secretario-migraciones-test-' . uniqid('', true);
        mkdir($dir, 0775, true);
        $this->dirsTemporales[] = $dir;
        foreach ($ficheros as $nombre => $contenido) {
            file_put_contents($dir . '/' . $nombre, $contenido);
        }

        return $dir;
    }
}
