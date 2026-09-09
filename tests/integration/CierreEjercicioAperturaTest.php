<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\CrearEjercicio;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\application\SincronizarConfiguracionConEjercicio;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\application\CrearApunte;
use src\apuntes\infrastructure\persistence\PdoApunteRepository;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\cierre\application\CerrarEjercicio;
use src\cierre\application\GenerarApertura;
use src\cierre\application\ReabrirEjercicio;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\shared\domain\value_objects\Dinero;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/** Fase 4b (D12): cierre de ejercicio y apertura automática regenerable. */
final class CierreEjercicioAperturaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME_TEST = 'secretario_test_cierre';

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

        (new ImportarExcelSecretario(
            $this->pdo,
            new PdoConfiguracionRepository($this->pdo),
            new PdoPersonaRepository($this->pdo),
            new PdoConceptoRepository($this->pdo),
            new PdoApunteRepository($this->pdo),
            new PdoPresupuestoRepository($this->pdo),
        ))->ejecutar($excelPath, true);

        self::assertSame(423, (int) $this->pdo->query('SELECT COUNT(*) FROM asientos')->fetchColumn());
    }

    public function testCierreAperturaYCorreccion(): void
    {
        $configRepo = new PdoConfiguracionRepository($this->pdo);
        $centroRepo = new PdoCentroRepository($this->pdo);
        $ejercicioRepo = new PdoEjercicioRepository($this->pdo);
        $asientoRepo = new PdoAsientoRepository($this->pdo);
        $cuentaRepo = new PdoCuentaRepository($this->pdo);
        $personaRepo = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($configRepo, $centroRepo, $ejercicioRepo);
        $contexto = $ambito->ejecutar();

        $ej2026 = $ejercicioRepo->porId($contexto->ejercicioId);
        self::assertNotNull($ej2026);
        self::assertNull($ej2026->ejercicioAnteriorId, '2026 importado no tiene anterior');

        $fechaFin2026 = $ej2026->fechaFin->format('Y-m-d');
        $snapshot2026 = $this->snapshotSaldos($asientoRepo, $contexto->centroId, $contexto->ejercicioId, $fechaFin2026);

        $cerrar = new CerrarEjercicio($ejercicioRepo);
        $ej2026Cerrado = $cerrar->ejecutar($contexto->ejercicioId);
        self::assertSame('cerrado', $ej2026Cerrado->estado);
        self::assertSame($fechaFin2026, $ej2026Cerrado->fechaCorte->format('Y-m-d'));

        $crearApunte = new CrearApunte(
            $asientoRepo,
            new PdoConceptoRepository($this->pdo),
            $personaRepo,
            $configRepo,
            $cuentaRepo,
            new PdoCuentaFisicaRepository($this->pdo),
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ambito,
            $ejercicioRepo,
        );
        $this->expectException(\RuntimeException::class);
        $crearApunte->ejecutar([
            'fecha' => $fechaFin2026,
            'cuenta' => 'G',
            'origen' => 'C',
            'concepto_codigo' => '201',
            'cantidad' => '1.00',
        ]);
    }

    public function testFlujoCompletoCierreApertura(): void
    {
        $configRepo = new PdoConfiguracionRepository($this->pdo);
        $centroRepo = new PdoCentroRepository($this->pdo);
        $ejercicioRepo = new PdoEjercicioRepository($this->pdo);
        $asientoRepo = new PdoAsientoRepository($this->pdo);
        $cuentaRepo = new PdoCuentaRepository($this->pdo);
        $personaRepo = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($configRepo, $centroRepo, $ejercicioRepo);
        $contexto = $ambito->ejecutar();

        $ej2026 = $ejercicioRepo->porId($contexto->ejercicioId);
        self::assertNotNull($ej2026);
        $fechaFin2026 = $ej2026->fechaFin->format('Y-m-d');
        $snapshot2026 = $this->snapshotSaldos($asientoRepo, $contexto->centroId, $contexto->ejercicioId, $fechaFin2026);

        $cerrar = new CerrarEjercicio($ejercicioRepo);
        $cerrar->ejecutar($contexto->ejercicioId);

        $generarApertura = new GenerarApertura($ejercicioRepo, $asientoRepo, $cuentaRepo);
        $syncConfig = new SincronizarConfiguracionConEjercicio($configRepo);
        $crearEjercicio = new CrearEjercicio($ejercicioRepo, $generarApertura, $syncConfig);

        $ej2027 = $crearEjercicio->ejecutar([
            'centro_id' => $contexto->centroId,
            'etiqueta' => '2027',
            'fecha_inicio' => '2027-01-01',
            'fecha_fin' => '2027-12-31',
        ]);
        self::assertNotNull($ej2027->id);
        self::assertSame($contexto->ejercicioId, $ej2027->ejercicioAnteriorId);

        $aperturas = $asientoRepo->listar($ej2027->id, ['tipo' => 'apertura']);
        self::assertCount(2, $aperturas, 'Debe haber asiento apertura P y G');
        foreach ($aperturas as $a) {
            $a->assertCuadre();
            self::assertSame('apertura', $a->tipo);
            self::assertSame('2027-01-01', $a->fecha->format('Y-m-d'));
        }

        $fechaInicio2027 = '2027-01-01';
        $snapshot2027 = $this->snapshotSaldos($asientoRepo, $contexto->centroId, $ej2027->id, $fechaInicio2027);

        $this->assertSaldosArrastrados($snapshot2026, $snapshot2027);

        $cfg = $configRepo->get();
        self::assertSame(2027, $cfg->anio);
        self::assertSame('2027-01-01', $cfg->fechaInicio->format('Y-m-d'));
        self::assertSame('2027-01-01', $cfg->fechaCierre->format('Y-m-d'));

        $realizado32 = $asientoRepo->realizadoPorConcepto(
            $contexto->centroId,
            $ej2027->id,
            'G',
            $fechaInicio2027,
            $fechaInicio2027,
        );
        $saldo32 = $this->saldoCuentaCodigo($asientoRepo, $contexto->centroId, $ej2027->id, $fechaInicio2027, 'G', '32');
        self::assertSame(-$saldo32->toCents(), $realizado32['32'] ?? 0, '613 G línea 32 = Haber−Debe de G/32');

        $generarApertura->ejecutar($ej2027->id);
        self::assertSame(2, $asientoRepo->contarApertura($ej2027->id), 'Regenerar no duplica aperturas');

        $reabrir = new ReabrirEjercicio($ejercicioRepo, $asientoRepo, $syncConfig);
        $reabrir->ejecutar($contexto->ejercicioId);

        $crearApunte = new CrearApunte(
            $asientoRepo,
            new PdoConceptoRepository($this->pdo),
            $personaRepo,
            $configRepo,
            $cuentaRepo,
            new PdoCuentaFisicaRepository($this->pdo),
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ambito,
            $ejercicioRepo,
        );
        $crearApunte->ejecutar([
            'fecha' => $fechaFin2026,
            'cuenta' => 'G',
            'origen' => 'C',
            'concepto_codigo' => '201',
            'cantidad' => '1.00',
        ]);

        $cerrar->ejecutar($contexto->ejercicioId);
        $reabrir->ejecutar($ej2027->id);
        $generarApertura->ejecutar($ej2027->id);

        $cajaG2027 = $this->saldoTesoreria($asientoRepo, $contexto->centroId, $ej2027->id, $fechaInicio2027, 'caja', 'G');
        $cajaG2026Original = $snapshot2026['tesoreria']['G']['caja'] ?? Dinero::zero();
        self::assertSame(
            $cajaG2026Original->sub(Dinero::fromInput('1.00'))->toString(),
            $cajaG2027->toString(),
            'Tras corregir 2026, caja G 2027 baja 1 €',
        );
    }

    /**
     * @return array{
     *   tesoreria: array<string, array<string, Dinero>>,
     *   cc: array<string, Dinero>,
     *   deudores_viv: Dinero,
     *   puente: array<string, Dinero>
     * }
     */
    private function snapshotSaldos(
        PdoAsientoRepository $repo,
        int $centroId,
        int $ejercicioId,
        string $hasta,
    ): array {
        $out = [
            'tesoreria' => ['P' => ['caja' => Dinero::zero(), 'banco' => Dinero::zero()], 'G' => ['caja' => Dinero::zero(), 'banco' => Dinero::zero()]],
            'cc' => [],
            'deudores_viv' => Dinero::zero(),
            'puente' => ['P' => Dinero::zero(), 'G' => Dinero::zero()],
        ];
        foreach ($repo->saldosPorCuenta($centroId, $ejercicioId, null, $hasta) as $row) {
            if ($row['tipo'] === 'tesoreria' && $row['saldo_cents'] !== 0) {
                $fisicaRepo = new PdoCuentaFisicaRepository($this->pdo);
                $tipo = 'banco';
                if ($row['cuenta_fisica_id'] !== null) {
                    foreach ($fisicaRepo->listarActivasDeCentro($centroId) as $f) {
                        if ($f->id === $row['cuenta_fisica_id']) {
                            $tipo = $f->tipo;
                        }
                    }
                }
                $libro = $row['libro'];
                $out['tesoreria'][$libro][$tipo] = $out['tesoreria'][$libro][$tipo]->add(Dinero::fromCents($row['saldo_cents']));
            }
            if ($row['tipo'] === 'personal' && str_starts_with($row['codigo'], 'CC.')) {
                $out['cc'][$row['codigo']] = Dinero::fromCents($row['saldo_cents']);
            }
            if ($row['codigo'] === 'DEUDORES.VIV') {
                $out['deudores_viv'] = Dinero::fromCents($row['saldo_cents']);
            }
            if ($row['codigo'] === 'PUENTE.LIBROS') {
                $out['puente'][$row['libro']] = Dinero::fromCents($row['saldo_cents']);
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $s2026 @param array<string, mixed> $s2027 */
    private function assertSaldosArrastrados(array $s2026, array $s2027): void
    {
        foreach (['P', 'G'] as $libro) {
            foreach (['caja', 'banco'] as $tipo) {
                self::assertSame(
                    $s2026['tesoreria'][$libro][$tipo]->toString(),
                    $s2027['tesoreria'][$libro][$tipo]->toString(),
                    sprintf('Tesorería %s %s', $libro, $tipo),
                );
            }
            self::assertSame(
                $s2026['puente'][$libro]->toString(),
                $s2027['puente'][$libro]->toString(),
                'PUENTE.LIBROS ' . $libro,
            );
        }
        self::assertSame($s2026['deudores_viv']->toString(), $s2027['deudores_viv']->toString());
        foreach ($s2026['cc'] as $codigo => $saldo) {
            self::assertArrayHasKey($codigo, $s2027['cc'], $codigo);
            self::assertSame($saldo->toString(), $s2027['cc'][$codigo]->toString(), $codigo);
        }
        if ($s2026['cc'] !== []) {
            $ejemplo = array_key_first($s2026['cc']);
            self::assertNotFalse($ejemplo);
        }
    }

    private function saldoTesoreria(
        PdoAsientoRepository $repo,
        int $centroId,
        int $ejercicioId,
        string $hasta,
        string $tipoFisica,
        string $libro,
    ): Dinero {
        $fisicaRepo = new PdoCuentaFisicaRepository($this->pdo);
        $total = Dinero::zero();
        foreach ($fisicaRepo->listarActivasDeCentro($centroId, $tipoFisica) as $f) {
            if ($f->id === null) {
                continue;
            }
            foreach ($repo->saldosPorCuenta($centroId, $ejercicioId, null, $hasta) as $row) {
                if ($row['tipo'] === 'tesoreria' && $row['cuenta_fisica_id'] === $f->id && $row['libro'] === $libro) {
                    $total = $total->add(Dinero::fromCents($row['saldo_cents']));
                }
            }
        }

        return $total;
    }

    private function saldoCuentaCodigo(
        PdoAsientoRepository $repo,
        int $centroId,
        int $ejercicioId,
        string $hasta,
        string $libro,
        string $codigo,
    ): Dinero {
        foreach ($repo->saldosPorCuenta($centroId, $ejercicioId, null, $hasta) as $row) {
            if ($row['libro'] === $libro && $row['codigo'] === $codigo) {
                return Dinero::fromCents($row['saldo_cents']);
            }
        }

        return Dinero::zero();
    }
}
