<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\administracion\application\ExportarConceptosPlan;
use src\administracion\application\ImportarConceptosPlan;
use src\administracion\application\ObtenerConceptosPlan;
use src\plan\infrastructure\persistence\PdoPlanContableRepository;
use src\plan\infrastructure\persistence\PdoPlanConceptoRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class ConceptosPlanAdminTest extends TestCase
{
    use BaseDeDatosAislada;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia('secretario_test_conceptos_plan');
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
    }

    public function testObtenerConceptosSembraCatalogoSiElPlanEstaVacio(): void
    {
        $planes = new PdoPlanContableRepository($this->pdo);
        $conceptos = new PdoPlanConceptoRepository($this->pdo);
        $plan = $planes->guardar(null, 'TEST1', 'Plan de prueba');
        self::assertSame([], $conceptos->listar($plan['id']));

        $lista = (new ObtenerConceptosPlan($planes, $conceptos))->ejecutar($plan['id']);
        self::assertGreaterThan(40, count($lista));
        self::assertNotEmpty(array_filter($lista, static fn (array $c): bool => $c['cuenta'] === 'P' && $c['codigo'] === '111'));
    }

    public function testExportarEImportarConceptos(): void
    {
        $planes = new PdoPlanContableRepository($this->pdo);
        $conceptos = new PdoPlanConceptoRepository($this->pdo);
        $obtener = new ObtenerConceptosPlan($planes, $conceptos);
        $exportar = new ExportarConceptosPlan($planes, $obtener);
        $importar = new ImportarConceptosPlan(
            $planes,
            new \src\administracion\application\GuardarConceptosPlan($conceptos, $this->pdo),
        );

        $origen = $planes->idPorCodigo('H16n');
        self::assertNotNull($origen);
        $payload = $exportar->ejecutar($origen);
        self::assertSame('H16n', $payload['plan']['codigo']);

        $destino = $planes->guardar(null, 'TEST2', 'Copia');
        $importados = $importar->ejecutar($destino['id'], $payload);
        self::assertSame(count($payload['conceptos']), count($importados));
    }
}
