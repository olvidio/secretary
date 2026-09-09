<?php

declare(strict_types=1);

namespace Tests\integration;

use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\application\CrearApunte;
use src\apuntes\infrastructure\persistence\PdoApunteRepository;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/**
 * Fase "2b Reparación importador" (docs/dev/plan_ampliaciones.md).
 *
 * Cierra el hueco de pruebas que dejó pasar el bug real: `GoldenMasterTest` y
 * `PlanDeCuentasCoberturaTest` recrean el esquema en cada ejecución, así que
 * siempre importan sobre una base VACÍA donde todavía no existen cuentas
 * personales (`cuentas.persona_id`) — la importación real, sobre una base ya
 * poblada y sembrada (como `secretario`), nunca se ejercitaba.
 *
 * Este test reproduce exactamente ese camino: importa dos veces seguidas sobre
 * la MISMA base, sin recrear el esquema entre pasadas, resembrando el ámbito
 * después de cada importación igual que hace `php bin/console.php import:excel`
 * (ver bin/console.php). Antes de la reparación, la segunda pasada fallaba con
 * `SQLSTATE[23503]` porque `ImportarExcelSecretario::ejecutar()` hacía
 * `personas->borrarTodos()` mientras `cuentas.persona_id` seguía referenciando
 * las filas que intentaba borrar.
 */
final class ImportacionIdempotenteTest extends TestCase
{
    use BaseDeDatosAislada;

    public function testImportarDosVecesSobreLaMismaBaseYaSembradaNoFallaNiDuplica(): void
    {
        $this->saltarSiNoHayPgsql();

        $excelPath = dirname(__DIR__, 2) . '/moviments2026.xlsm';
        if (!is_readable($excelPath)) {
            self::markTestSkipped(
                'Falta moviments2026.xlsm en la raíz del repositorio; no se puede reproducir la reimportación.'
            );
        }

        try {
            $pdo = $this->prepararBaseDeTestVacia('secretario_test_reimport');
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base de datos de test aislada: ' . $e->getMessage());
        }

        (new SchemaInstaller($pdo))->install();

        $configRepo = new PdoConfiguracionRepository($pdo);
        $personaRepo = new PdoPersonaRepository($pdo);
        $conceptoRepo = new PdoConceptoRepository($pdo);
        $apunteRepo = new PdoApunteRepository($pdo);
        $presupuestoRepo = new PdoPresupuestoRepository($pdo);

        $importar = new ImportarExcelSecretario(
            $pdo,
            $configRepo,
            $personaRepo,
            $conceptoRepo,
            $apunteRepo,
            $presupuestoRepo,
        );

        // --- Primera pasada: base recién instalada (con ámbito placeholder), como el
        // `db:install` + `import:excel` de una instalación nueva. ---
        $resultado1 = $importar->ejecutar($excelPath, true);
        AmbitoSeeder::sembrar($pdo); // mismo orden que bin/console.php tras la reparación

        $centro = (new PdoCentroRepository($pdo))->porCodigo('Montagut');
        self::assertNotNull($centro, 'AmbitoSeeder debía haber creado el centro Montagut tras la primera importación');

        $personasAntes = $this->contarPersonas($pdo);
        $activasAntes = $this->contarPersonasActivas($pdo);
        $cuentasAntes = $this->contarCuentas($pdo);
        $apuntesAntes = (int) $pdo->query('SELECT COUNT(*) FROM apuntes')->fetchColumn();
        $mapaCcAntes = $this->mapaCuentasPersonales($pdo);

        self::assertGreaterThan(0, $personasAntes, 'La primera importación debía haber creado personas');
        self::assertNotEmpty($mapaCcAntes, 'La primera importación debía haber creado cuentas CC.* por persona');

        $asientosAntes = (int) $pdo->query('SELECT COUNT(*) FROM asientos WHERE anulado_at IS NULL')->fetchColumn();
        self::assertGreaterThan(0, $asientosAntes, 'La importación debe generar asientos');

        // --- Segunda pasada: exactamente lo que fallaba antes de la reparación. La
        // base ya tiene personas, apuntes, presupuesto Y cuentas (incluidas las
        // cuentas personales CC.<INICIALES> que referencian a `personas` por FK). ---
        $resultado2 = $importar->ejecutar($excelPath, true);
        AmbitoSeeder::sembrar($pdo);

        // 1. La segunda importación no falla (ya lo garantiza no haber lanzado
        // excepción arriba) y devuelve los mismos recuentos que la primera.
        self::assertSame($resultado1, $resultado2, 'Reimportar el mismo Excel debe dar los mismos recuentos');

        // 2. No se duplican personas ni cuentas.
        self::assertSame($personasAntes, $this->contarPersonas($pdo), 'No debe duplicarse ninguna persona');
        self::assertSame(
            $activasAntes,
            $this->contarPersonasActivas($pdo),
            'Todas las personas del Excel deben seguir activas tras la segunda importación'
        );
        self::assertSame($cuentasAntes, $this->contarCuentas($pdo), 'No debe duplicarse ninguna cuenta');

        // 3. Las cuentas corrientes siguen apuntando a la persona correcta (mismo
        // persona_id que antes para cada código CC.<INICIALES>).
        self::assertSame(
            $mapaCcAntes,
            $this->mapaCuentasPersonales($pdo),
            'Las cuentas CC.<INICIALES> deben seguir apuntando exactamente a la misma persona'
        );

        // 4. El número de apuntes es el mismo que tras la primera importación.
        $apuntesDespues = (int) $pdo->query('SELECT COUNT(*) FROM apuntes')->fetchColumn();
        self::assertSame($apuntesAntes, $apuntesDespues, 'El número de apuntes no debe cambiar al reimportar');

        $asientosDespues = (int) $pdo->query('SELECT COUNT(*) FROM asientos WHERE anulado_at IS NULL')->fetchColumn();
        self::assertSame($asientosAntes, $asientosDespues, 'Reimportar no debe duplicar asientos');

        $informe2 = $importar->ultimoInforme();
        self::assertSame(0, $informe2['altas']);
        self::assertSame(0, $informe2['cambios']);
        self::assertSame(0, $informe2['bajas']);
        self::assertGreaterThan(0, $informe2['saltados']);

        $fechasDistintas = (int) $pdo->query(
            "SELECT COUNT(*) FROM asientos WHERE origen = 'import' AND fecha_operacion IS DISTINCT FROM fecha"
        )->fetchColumn();
        self::assertSame(0, $fechasDistintas, 'Excel: fecha_operacion = fecha');

        $idsTrasSegunda = $this->idsAsientosActivos($pdo);
        $resultado3 = $importar->ejecutar($excelPath, true);
        AmbitoSeeder::sembrar($pdo);
        self::assertSame($resultado1, $resultado3, 'Tercera importación: mismos recuentos');
        self::assertSame($idsTrasSegunda, $this->idsAsientosActivos($pdo), 'Los id de asiento se conservan');

        $duplicadas = (int) $pdo->query(
            'SELECT COUNT(*) FROM (SELECT iniciales FROM personas GROUP BY iniciales HAVING COUNT(*) > 1) t'
        )->fetchColumn();
        self::assertSame(0, $duplicadas, 'No debe haber iniciales duplicadas en personas');
    }

    public function testAsientoManualSobreviveCambioDeFilaYBajaDeImport(): void
    {
        $this->saltarSiNoHayPgsql();
        $excelPath = dirname(__DIR__, 2) . '/moviments2026.xlsm';
        if (!is_readable($excelPath)) {
            self::markTestSkipped('Falta moviments2026.xlsm en la raíz del repositorio.');
        }
        try {
            $pdo = $this->prepararBaseDeTestVacia('secretario_test_import_d8');
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base de datos de test aislada: ' . $e->getMessage());
        }

        (new SchemaInstaller($pdo))->install();
        $importar = $this->nuevoImportador($pdo);
        $importar->ejecutar($excelPath, true);
        AmbitoSeeder::sembrar($pdo);

        $configRepo = new PdoConfiguracionRepository($pdo);
        $personaRepo = new PdoPersonaRepository($pdo);
        $centroRepo = new PdoCentroRepository($pdo);
        $ejercicioRepo = new PdoEjercicioRepository($pdo);
        $asientoRepo = new PdoAsientoRepository($pdo);
        $cuentaRepo = new PdoCuentaRepository($pdo);
        $fisicaRepo = new PdoCuentaFisicaRepository($pdo);
        $ambito = new ResolverAmbitoActual($configRepo, $centroRepo, $ejercicioRepo);
        $contexto = $ambito->ejecutar();
        $cfg = $configRepo->get();

        $crear = new CrearApunte(
            $asientoRepo,
            new PdoConceptoRepository($pdo),
            $personaRepo,
            $configRepo,
            $cuentaRepo,
            $fisicaRepo,
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ambito,
            $ejercicioRepo,
        );
        $crear->ejecutar([
            'fecha' => $cfg->fechaCierre->format('Y-m-d'),
            'cuenta' => 'G',
            'origen' => 'C',
            'concepto_codigo' => '201',
            'cantidad' => '1.00',
        ]);
        $idManual = (int) $pdo->query(
            "SELECT id FROM asientos WHERE origen = 'manual' ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        self::assertGreaterThan(0, $idManual);

        $crear->ejecutar([
            'fecha' => $cfg->fechaCierre->format('Y-m-d'),
            'cuenta' => 'G',
            'origen' => 'C',
            'concepto_codigo' => '201',
            'cantidad' => '2.00',
        ]);
        $stH = $pdo->prepare(
            "SELECT id FROM asientos WHERE origen = 'manual' AND id <> :m ORDER BY id DESC LIMIT 1"
        );
        $stH->execute([':m' => $idManual]);
        $idHuerfano = (int) $stH->fetchColumn();
        $pdo->prepare("UPDATE asientos SET origen = 'import' WHERE id = :id")->execute([':id' => $idHuerfano]);
        $pdo->prepare(
            'INSERT INTO import_filas (ejercicio_id, hoja, fila, hash_contenido, asiento_id)
             VALUES (:ej, :hoja, 99999, :h, :a)'
        )->execute([
            ':ej' => $contexto->ejercicioId,
            ':hoja' => ImportarExcelSecretario::HOJA_TALONARIOS,
            ':h' => 'huerfano',
            ':a' => $idHuerfano,
        ]);

        $stFila = $pdo->query(
            'SELECT id, asiento_id FROM import_filas WHERE asiento_id IS NOT NULL AND fila <> 99999 ORDER BY fila LIMIT 1'
        );
        $fila = $stFila->fetch();
        self::assertIsArray($fila);
        $asientoActualizable = (int) $fila['asiento_id'];
        $pdo->prepare('UPDATE import_filas SET hash_contenido = :h WHERE id = :id')->execute([
            ':h' => 'stale',
            ':id' => (int) $fila['id'],
        ]);

        $antesActivos = $this->idsAsientosActivos($pdo);
        self::assertContains($idManual, $antesActivos);
        self::assertContains($idHuerfano, $antesActivos);

        $importar->ejecutar($excelPath, true, true);
        $informeDry = $importar->ultimoInforme();
        self::assertSame(1, $informeDry['cambios']);
        self::assertSame(1, $informeDry['bajas']);
        self::assertSame($antesActivos, $this->idsAsientosActivos($pdo), '--dry-run no debe escribir asientos');
        $staleSigue = (int) $pdo->query(
            "SELECT COUNT(*) FROM import_filas WHERE hash_contenido = 'stale'"
        )->fetchColumn();
        self::assertSame(1, $staleSigue, '--dry-run no debe actualizar hashes');

        $importar->ejecutar($excelPath, true);
        $informe = $importar->ultimoInforme();
        self::assertSame(1, $informe['cambios']);
        self::assertSame(1, $informe['bajas']);
        self::assertContains($idManual, $this->idsAsientosActivos($pdo), 'El asiento manual debe sobrevivir');
        self::assertNotContains($idHuerfano, $this->idsAsientosActivos($pdo), 'La fila huérfana de import se anula');
        $sigue = $asientoRepo->porId($asientoActualizable);
        self::assertNotNull($sigue, 'El asiento cuyo hash cambió se actualiza, no se recrea');
        $stAnulado = $pdo->prepare(
            'SELECT COUNT(*) FROM asientos WHERE id = :id AND anulado_at IS NOT NULL'
        );
        $stAnulado->execute([':id' => $idHuerfano]);
        self::assertSame(1, (int) $stAnulado->fetchColumn());
    }

    private function nuevoImportador(\PDO $pdo): ImportarExcelSecretario
    {
        return new ImportarExcelSecretario(
            $pdo,
            new PdoConfiguracionRepository($pdo),
            new PdoPersonaRepository($pdo),
            new PdoConceptoRepository($pdo),
            new PdoApunteRepository($pdo),
            new PdoPresupuestoRepository($pdo),
        );
    }

    /** @return list<int> */
    private function idsAsientosActivos(\PDO $pdo): array
    {
        $rows = $pdo->query('SELECT id FROM asientos WHERE anulado_at IS NULL ORDER BY id')->fetchAll();
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    private function contarPersonas(\PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM personas')->fetchColumn();
    }

    private function contarPersonasActivas(\PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM personas WHERE activo')->fetchColumn();
    }

    private function contarCuentas(\PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM cuentas')->fetchColumn();
    }

    /** @return array<string, int> codigo => persona_id */
    private function mapaCuentasPersonales(\PDO $pdo): array
    {
        $rows = $pdo->query(
            "SELECT codigo, persona_id FROM cuentas WHERE codigo LIKE 'CC.%' ORDER BY codigo"
        )->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['codigo']] = (int) $row['persona_id'];
        }

        return $out;
    }
}
