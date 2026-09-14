<?php

declare(strict_types=1);

namespace Tests\integration;

use PHPUnit\Framework\TestCase;
use src\shared\infrastructure\persistence\PostgresDumper;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/** Copia y restauración sobre una base aislada, nunca sobre `secretario`. */
final class PostgresDumperTest extends TestCase
{
    use BaseDeDatosAislada;

    public function testBackupYRestore(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia('secretario_test_backup');
        (new SchemaInstaller($pdo))->install();
        $conceptosAntes = (int) $pdo->query('SELECT COUNT(*) FROM conceptos')->fetchColumn();
        self::assertGreaterThan(0, $conceptosAntes);

        $dump = sys_get_temp_dir() . '/secretario_test_' . uniqid('', true) . '.sql';
        $dumper = PostgresDumper::forConnection(
            '127.0.0.1',
            5455,
            'secretario_test_backup',
            'secretario',
            'secretario',
            PostgresDumper::serverMajorVersion($pdo),
        );

        try {
            $dumper->backup($dump);
            self::assertFileExists($dump);
            self::assertGreaterThan(0, filesize($dump));

            $pdo->exec('DROP SCHEMA public CASCADE; CREATE SCHEMA public');
            self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM pg_tables WHERE schemaname = 'public'")->fetchColumn());

            $dumper->restore($dump);
            $conceptosDespues = (int) $pdo->query('SELECT COUNT(*) FROM conceptos')->fetchColumn();
            self::assertSame($conceptosAntes, $conceptosDespues);
        } finally {
            if (is_file($dump)) {
                unlink($dump);
            }
        }
    }
}
