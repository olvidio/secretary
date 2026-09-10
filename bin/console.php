<?php

declare(strict_types=1);

use src\acceso\infrastructure\persistence\AccesoSeeder;
use src\apuntes\infrastructure\persistence\PlantillaApunteSeeder;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\plan\infrastructure\persistence\PlanContableSeeder;
use src\asientos\application\ConvertirApuntesAAsientos;
use src\importacion\application\ImportarExcelSecretario;
use src\personal\infrastructure\persistence\Nivel1Seeder;
use src\shared\infrastructure\Kernel;
use src\shared\infrastructure\persistence\MigrationRunner;
use src\shared\infrastructure\persistence\SchemaInstaller;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$argv = $_SERVER['argv'] ?? [];
if (!is_array($argv)) {
    $argv = [];
}
$cmd = isset($argv[1]) && is_string($argv[1]) ? $argv[1] : 'help';

// Los errores previstos (checksum de una migración alterada, DB_DRIVER no soportado,
// DSN ausente) son avisos deliberados, no caídas: se muestran limpios y con código 1,
// sin traza, para que no parezcan un fallo del programa.
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    if ((getenv('APP_DEBUG') === '1') && $e->getPrevious() !== null) {
        fwrite(STDERR, 'Causa: ' . $e->getPrevious()->getMessage() . "\n");
    }
    exit(1);
});

$kernel = Kernel::boot();
$pdo = $kernel->pdo();

if ($cmd === 'db:migrate') {
    $runner = new MigrationRunner($pdo, $root . '/migraciones');
    $antes = array_column($runner->estado()['aplicadas'], 'version');
    $aplicadas = $runner->migrar();
    // AmbitoSeeder es idempotente y no-op si todavía no existe fila en `configuracion`
    // (instalación nueva sin sembrar aún); en `secretario` sí existe, así que aquí es
    // donde se puebla el ámbito (centros/ejercicios/cuentas_fisicas/cuentas) sobre datos
    // ya existentes, sin pasar por `SchemaInstaller::install()` (que sería reinstalar).
    PlanContableSeeder::sembrar($pdo);
    AmbitoSeeder::sembrar($pdo);
    PlanContableSeeder::sembrar($pdo);
    AccesoSeeder::sembrar($pdo);
    PlantillaApunteSeeder::sembrar($pdo);
    Nivel1Seeder::sembrar($pdo);
    if ($aplicadas !== []) {
        fwrite(STDOUT, 'Aplicadas: ' . implode(', ', $aplicadas) . "\n");
        exit(0);
    }
    $despues = array_column($runner->estado()['aplicadas'], 'version');
    $baseline = array_diff($despues, $antes);
    if ($baseline !== []) {
        // Base preexistente (p. ej. `secretario`, creada antes de este runner): se marca
        // como aplicada sin ejecutar su SQL. Ver docs/dev/migraciones.md.
        fwrite(STDOUT, 'Marcadas como base preexistente, sin ejecutar: ' . implode(', ', $baseline) . "\n");
    } else {
        fwrite(STDOUT, "Nada que migrar; el esquema ya está al día.\n");
    }
    exit(0);
}

if ($cmd === 'db:status') {
    $runner = new MigrationRunner($pdo, $root . '/migraciones');
    $estado = $runner->estado();
    fwrite(STDOUT, "Aplicadas:\n");
    if ($estado['aplicadas'] === []) {
        fwrite(STDOUT, "  (ninguna)\n");
    }
    foreach ($estado['aplicadas'] as $fila) {
        fwrite(STDOUT, sprintf("  %s  %s\n", $fila['version'], $fila['aplicada_at']));
    }
    fwrite(STDOUT, "Pendientes:\n");
    if ($estado['pendientes'] === []) {
        fwrite(STDOUT, "  (ninguna)\n");
    }
    foreach ($estado['pendientes'] as $version) {
        fwrite(STDOUT, "  $version\n");
    }
    exit(0);
}

if ($cmd === 'db:install') {
    // db:migrate + semillas (conceptos, usuario, configuración inicial).
    (new SchemaInstaller($pdo))->install();
    fwrite(STDOUT, "Esquema instalado (migraciones + semillas).\n");
    exit(0);
}

if ($cmd === 'import:excel') {
    (new SchemaInstaller($pdo))->install();
    $path = $root . '/moviments2026.xlsm';
    $dryRun = false;
    $centro = null;
    $ejercicio = null;
    for ($i = 2; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (!is_string($arg)) {
            continue;
        }
        if ($arg === '--dry-run') {
            $dryRun = true;
            continue;
        }
        if (str_starts_with($arg, '--centro=')) {
            $centro = substr($arg, strlen('--centro='));
            continue;
        }
        if (str_starts_with($arg, '--ejercicio=')) {
            $ejercicio = substr($arg, strlen('--ejercicio='));
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            fwrite(STDOUT, "Uso: php bin/console.php import:excel [fichero.xlsm] [--dry-run] [--centro=CODIGO] [--ejercicio=ETIQUETA]\n");
            exit(0);
        }
        if (str_starts_with($arg, '--')) {
            fwrite(STDERR, "Opción desconocida: $arg\n");
            exit(1);
        }
        $path = $arg;
    }
    $imp = $kernel->container()->get(ImportarExcelSecretario::class);
    $res = $imp->ejecutar($path, true, $dryRun, $centro, $ejercicio);
    // `SchemaInstaller::install()` siembra el ámbito ANTES de importar, con los datos
    // placeholder de `configuracion` (ver docs/dev/ambito.md, §3): en una base nueva
    // (db:install + import:excel) el centro/ejercicio quedarían con esos datos
    // placeholder y las personas recién importadas no tendrían todavía su cuenta
    // corriente `CC.<INICIALES>`. Se vuelve a sembrar aquí, ya con la configuración
    // real importada, para que la Fase 2b (ver plan_ampliaciones.md) quede completa.
    // AmbitoSeeder es idempotente, así que en la base real `secretario` (donde el
    // ámbito ya estaba poblado por un `db:migrate` anterior) esta llamada solo
    // sincroniza personas nuevas/reactivadas, sin duplicar nada.
    if (!$dryRun) {
        AmbitoSeeder::sembrar($pdo);
        AccesoSeeder::sembrar($pdo);
        Nivel1Seeder::sembrar($pdo);
    }
    $informe = $imp->ultimoInforme();
    fwrite(STDOUT, json_encode($res + $informe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    exit(0);
}

if ($cmd === 'asientos:convertir') {
    (new SchemaInstaller($pdo))->install();
    $convertir = $kernel->container()->get(ConvertirApuntesAAsientos::class);
    $res = $convertir->ejecutar('import');
    fwrite(STDOUT, json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    exit(0);
}

fwrite(STDOUT, "Uso:\n"
    . "  php bin/console.php db:migrate\n"
    . "  php bin/console.php db:status\n"
    . "  php bin/console.php db:install\n"
    . "  php bin/console.php import:excel [fichero.xlsm] [--dry-run] [--centro=CODIGO] [--ejercicio=ETIQUETA]\n"
    . "  php bin/console.php asientos:convertir\n");
exit(1);
