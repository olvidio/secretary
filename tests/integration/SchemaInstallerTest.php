<?php

declare(strict_types=1);

namespace Tests\integration;

use PHPUnit\Framework\TestCase;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/**
 * Corre `SchemaInstaller::install()` (migraciones + semillas) contra `secretario_test`,
 * nunca contra la base de desarrollo `secretario`. Mismo mecanismo de aislamiento que
 * `GoldenMasterTest`, factorizado en `Tests\Soporte\BaseDeDatosAislada`.
 */
final class SchemaInstallerTest extends TestCase
{
    use BaseDeDatosAislada;

    public function testInstall(): void
    {
        $this->saltarSiNoHayPgsql();
        $pdo = $this->prepararBaseDeTestVacia();

        (new SchemaInstaller($pdo))->install();

        $n = (int) $pdo->query('SELECT COUNT(*) FROM conceptos')->fetchColumn();
        self::assertGreaterThan(40, $n);
        $cfg = $pdo->query('SELECT centro FROM configuracion WHERE id = 1')->fetchColumn();
        self::assertNotFalse($cfg);
        $rutas = (int) $pdo->query('SELECT COUNT(*) FROM rutas_acceso')->fetchColumn();
        self::assertGreaterThan(20, $rutas);
        $ids = (int) $pdo->query('SELECT COUNT(*) FROM identidades')->fetchColumn();
        self::assertGreaterThanOrEqual(1, $ids);
    }
}
