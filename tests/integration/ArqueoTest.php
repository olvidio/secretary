<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\arqueo\application\GuardarArqueo;
use src\arqueo\infrastructure\persistence\PdoArqueoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\apuntes\infrastructure\persistence\PdoApunteRepository;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\informes\application\ObtenerResumen613;
use src\informes\infrastructure\persistence\PdoInforme613MesRepository;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\plan\infrastructure\persistence\PdoPartidaLaboresRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class ArqueoTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME_TEST = 'secretario_test_arqueo';

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        $excelPath = dirname(__DIR__, 2) . '/moviments2026.xlsm';
        if (!is_readable($excelPath)) {
            self::markTestSkipped('Falta moviments2026.xlsm');
        }
        try {
            $this->pdo = $this->prepararBaseDeTestVacia(self::DB_NAME_TEST);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();

        $configRepo = new PdoConfiguracionRepository($this->pdo);
        (new ImportarExcelSecretario(
            $this->pdo,
            $configRepo,
            new PdoPersonaRepository($this->pdo),
            new PdoConceptoRepository($this->pdo),
            new PdoApunteRepository($this->pdo),
            new PdoPresupuestoRepository($this->pdo),
        ))->ejecutar($excelPath, true);
    }

    public function testGuardarArqueoYProponerEn613G(): void
    {
        $configRepo = new PdoConfiguracionRepository($this->pdo);
        $ambito = new ResolverAmbitoActual(
            $configRepo,
            new PdoCentroRepository($this->pdo),
            new PdoEjercicioRepository($this->pdo),
        );
        $contexto = $ambito->ejecutar();
        $cfg = $configRepo->get();
        $fecha = $cfg->fechaCierre->format('Y-m-d');

        $fisicaRepo = new PdoCuentaFisicaRepository($this->pdo);
        $caja = $fisicaRepo->listarActivasDeCentro($contexto->centroId, 'caja')[0] ?? null;
        self::assertNotNull($caja?->id);

        $arqueoRepo = new PdoArqueoRepository($this->pdo);
        $guardar = new GuardarArqueo($arqueoRepo, $ambito);
        $arqueo = $guardar->ejecutar('G', $fecha, [
            'billetes' => [0, 0, 2, 1, 0, 0, 0],
            'monedas' => [0, 0, 0, 0, 0, 0, 0, 0],
            'vales' => [],
            'cheques' => [],
        ], $caja->id);

        self::assertSame('250.00', $arqueo->total->toString());
        self::assertSame($contexto->ejercicioId, $arqueo->ejercicioId);

        $enCierre = $arqueoRepo->ultimoEnCierre($contexto->ejercicioId, 'G', $cfg->fechaCierre);
        self::assertNotNull($enCierre);
        self::assertSame('250.00', $enCierre->total->toString());

        $resumen = $this->resumen613($configRepo, $ambito, $arqueoRepo);
        $payload = $resumen->ejecutar('G');
        self::assertSame('250,00', $payload['dinero_arqueo_caja']);
        self::assertSame('250.00', $payload['arqueo_total']);
    }

    public function testArqueoPAlimenta613G(): void
    {
        $configRepo = new PdoConfiguracionRepository($this->pdo);
        $ambito = new ResolverAmbitoActual(
            $configRepo,
            new PdoCentroRepository($this->pdo),
            new PdoEjercicioRepository($this->pdo),
        );
        $contexto = $ambito->ejecutar();
        $cfg = $configRepo->get();
        $fecha = $cfg->fechaCierre->format('Y-m-d');
        $fisicaRepo = new PdoCuentaFisicaRepository($this->pdo);
        $caja = $fisicaRepo->listarActivasDeCentro($contexto->centroId, 'caja')[0] ?? null;
        self::assertNotNull($caja?->id);

        $arqueoRepo = new PdoArqueoRepository($this->pdo);
        $guardar = new GuardarArqueo($arqueoRepo, $ambito);
        $guardar->ejecutar('P', $fecha, [
            'billetes' => [0, 0, 0, 0, 1, 0, 0],
            'monedas' => [0, 0, 0, 0, 0, 0, 0, 0],
            'vales' => [],
            'cheques' => [],
        ], $caja->id);

        $payload = $this->resumen613($configRepo, $ambito, $arqueoRepo)->ejecutar('G');
        self::assertSame('20,00', $payload['dinero_arqueo_caja']);
    }

    private function resumen613(
        PdoConfiguracionRepository $configRepo,
        ResolverAmbitoActual $ambito,
        PdoArqueoRepository $arqueoRepo,
    ): ObtenerResumen613 {
        return new ObtenerResumen613(
            $configRepo,
            new PdoAsientoRepository($this->pdo),
            new PdoPresupuestoRepository($this->pdo),
            new PdoPersonaRepository($this->pdo),
            $ambito,
            new PdoPartidaLaboresRepository($this->pdo),
            new PdoInforme613MesRepository($this->pdo),
            $arqueoRepo,
            new PdoCuentaFisicaRepository($this->pdo),
        );
    }
}
