<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\apuntes\infrastructure\persistence\PdoApunteRepository;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/**
 * Fase 2 (docs/dev/plan_ampliaciones.md, D2): cobertura del plan de cuentas
 * generado por AmbitoSeeder sobre los apuntes reales importados de
 * moviments2026.xlsm. Para cada pareja (cuenta, concepto_codigo) que existe de
 * verdad en `apuntes` debe existir una cuenta imputable en `cuentas` con ese
 * mismo código dentro del plan maestro (persona_id IS NULL); si no existe, es
 * un código huérfano y debe reportarse explícitamente, nunca inventarse una
 * cuenta para "arreglarlo" en silencio.
 *
 * Historial: hasta el 2026-09-08 existía exactamente un huérfano conocido,
 * (P, 11) — el apunte real con id 159, una nómina de Agustí Fontarnau con un
 * dígito perdido en la celda E160 de la hoja Talonarios (`P/11` no existe en el
 * plan; era `P/111`, "Trabajo"). Corregido el dato de origen en
 * `moviments2026.xlsm` (fase "2b Reparación importador" de
 * `docs/dev/plan_ampliaciones.md`) y reimportado, el huérfano desaparece. A
 * partir de ahí este test exige **cero** huérfanos y falla ante cualquiera
 * nuevo, en vez de tolerar una lista fija de anomalías conocidas.
 */
final class PlanDeCuentasCoberturaTest extends TestCase
{
    use BaseDeDatosAislada;

    public function testTodosLosApuntesRealesTienenCuentaImputableSinHuerfanos(): void
    {
        $this->saltarSiNoHayPgsql();

        $excelPath = dirname(__DIR__, 2) . '/moviments2026.xlsm';
        if (!is_readable($excelPath)) {
            self::markTestSkipped(
                'Falta moviments2026.xlsm en la raíz del repositorio; no se puede comprobar la cobertura real.'
            );
        }

        try {
            $pdo = $this->prepararBaseDeTestVacia();
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
        $importar->ejecutar($excelPath, true);

        // La importación sobrescribe `configuracion` con los datos reales (centro
        // Montagut, etc.) DESPUÉS de que SchemaInstaller::install() sembrara el ámbito
        // sobre la configuración placeholder inicial; hay que volver a sembrar para que
        // el plan de cuentas se genere para el centro real. En producción esto no pasa:
        // `configuracion` ya tiene los datos reales antes de que se llame a AmbitoSeeder.
        AmbitoSeeder::sembrar($pdo);

        $centro = (new PdoCentroRepository($pdo))->porCodigo('Montagut');
        self::assertNotNull($centro, 'AmbitoSeeder debía haber creado el centro Montagut tras la importación');

        $cuentaRepo = new PdoCuentaRepository($pdo);

        $total = (int) $pdo->query('SELECT COUNT(*) FROM apuntes')->fetchColumn();
        self::assertSame(423, $total, 'Se esperaba el mismo número de apuntes reales de moviments2026.xlsm');

        $pares = $pdo->query(
            'SELECT DISTINCT cuenta, concepto_codigo FROM apuntes ORDER BY cuenta, concepto_codigo'
        )->fetchAll();

        $huerfanos = [];
        foreach ($pares as $par) {
            $cuenta = (string) $par['cuenta'];
            $codigo = (string) $par['concepto_codigo'];
            $cuentaContable = $cuentaRepo->buscar((int) $centro->id, null, $cuenta, $codigo);
            if ($cuentaContable === null || !$cuentaContable->imputable) {
                $huerfanos[] = ['cuenta' => $cuenta, 'concepto_codigo' => $codigo];
            }
        }

        self::assertSame(
            [],
            $huerfanos,
            'Se esperaban cero códigos huérfanos (el único conocido, P/11, se corrigió en el '
            . 'origen — ver docs/dev/plan_ampliaciones.md, fase "2b Reparación importador"). '
            . 'Si aparece uno nuevo, documéntalo en docs/dev/ambito.md; no lo silencies aquí.'
        );
    }
}
