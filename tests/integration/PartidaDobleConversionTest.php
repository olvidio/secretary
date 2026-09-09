<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\infrastructure\persistence\PdoApunteRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/**
 * Fase 3 OLA 1: conversión apuntes → asientos sobre datos reales (moviments2026.xlsm).
 * Base aislada `secretario_test_asientos`; no toca golden master ni `secretario`.
 */
final class PartidaDobleConversionTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME_TEST = 'secretario_test_asientos';

    public function testConversionApuntesRealesGeneraAsientosCuadrados(): void
    {
        $this->saltarSiNoHayPgsql();

        $excelPath = dirname(__DIR__, 2) . '/moviments2026.xlsm';
        if (!is_readable($excelPath)) {
            self::markTestSkipped(
                'Falta moviments2026.xlsm en la raíz del repositorio; no se puede probar la conversión real.'
            );
        }

        try {
            $pdo = $this->prepararBaseDeTestVacia(self::DB_NAME_TEST);
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
        AmbitoSeeder::sembrar($pdo);

        $centroRepo = new PdoCentroRepository($pdo);
        $ejercicioRepo = new PdoEjercicioRepository($pdo);
        $centro = $centroRepo->porCodigo('Montagut');
        self::assertNotNull($centro?->id, 'AmbitoSeeder debía haber creado el centro Montagut tras la importación');
        $ejercicio = $ejercicioRepo->abiertoDe($centro->id);
        self::assertNotNull($ejercicio?->id);

        $asientoRepo = new PdoAsientoRepository($pdo);
        $informe = $importar->ultimoInforme();
        $totalApuntes = (int) $pdo->query('SELECT COUNT(*) FROM apuntes')->fetchColumn();
        self::assertSame(
            $totalApuntes - (int) $informe['omitidos_concepto_9'] - (int) $informe['traspasos_fusionados'],
            (int) $informe['asientos'],
            'Número de asientos = apuntes − omitidos 9 − traspasos fusionados'
        );
        self::assertSame(
            (int) $informe['asientos'],
            count($asientoRepo->listarPorEjercicio($ejercicio->id)),
        );

        $descuadrados = $pdo->query(
            'SELECT asiento_id FROM movimientos GROUP BY asiento_id HAVING SUM(debe) <> SUM(haber)'
        )->fetchAll();
        self::assertSame([], $descuadrados, 'Ningún asiento descuadrado');

        $movimientosCuenta9 = $pdo->query(
            "SELECT m.id FROM movimientos m
             JOIN cuentas c ON c.id = m.cuenta_id
             WHERE c.codigo = '9' AND c.persona_id IS NULL AND c.imputable = FALSE"
        )->fetchAll();
        self::assertSame([], $movimientosCuenta9, 'Cero movimientos en la cuenta agregada no imputable P/9');

        $tesoreriaCoherente = $pdo->query(
            "SELECT c.libro,
                    SUM(m.debe) AS total_debe,
                    SUM(m.haber) AS total_haber
             FROM movimientos m
             JOIN cuentas c ON c.id = m.cuenta_id
             WHERE c.tipo = 'tesoreria'
             GROUP BY c.libro, c.id
             HAVING SUM(m.debe) < 0 OR SUM(m.haber) < 0"
        )->fetchAll();
        self::assertSame([], $tesoreriaCoherente, 'Saldos de tesorería internamente coherentes');

        $globalCuadre = $pdo->query(
            'SELECT SUM(debe) AS d, SUM(haber) AS h FROM movimientos'
        )->fetch();
        self::assertIsArray($globalCuadre);
        self::assertSame((int) $globalCuadre['d'], (int) $globalCuadre['h'], 'Cuadre global debe = haber');
    }
}
