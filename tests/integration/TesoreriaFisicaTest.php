<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\CrearCuentaFisica;
use src\ambito\application\DesactivarCuentaFisica;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\application\CrearApunte;
use src\apuntes\infrastructure\persistence\PdoApunteRepository;
use src\asientos\application\RegistrarPrestamoEntreLibros;
use src\asientos\application\RegistrarTraspasoTesoreria;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\informes\application\CalcularSaldos;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\shared\domain\value_objects\Dinero;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/** Fase 4 (D10): tesorería física compartida, multibanco, préstamos entre libros. */
final class TesoreriaFisicaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME_TEST = 'secretario_test_tesoreria';

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

        self::assertSame(423, (int) $this->pdo->query('SELECT COUNT(*) FROM asientos')->fetchColumn());
    }

    public function testFlujoTesoreriaFisicaMultibanco(): void
    {
        $configRepo = new PdoConfiguracionRepository($this->pdo);
        $centroRepo = new PdoCentroRepository($this->pdo);
        $ejercicioRepo = new PdoEjercicioRepository($this->pdo);
        $cuentaRepo = new PdoCuentaRepository($this->pdo);
        $fisicaRepo = new PdoCuentaFisicaRepository($this->pdo);
        $asientoRepo = new PdoAsientoRepository($this->pdo);
        $personaRepo = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($configRepo, $centroRepo, $ejercicioRepo);
        $contexto = $ambito->ejecutar();

        $cfg = $configRepo->get();
        $desde = $cfg->fechaInicio->format('Y-m-d');
        $hasta = $cfg->fechaCierre->format('Y-m-d');

        $saldoCajaP = $this->saldoTesoreriaFisica($asientoRepo, $contexto->centroId, $contexto->ejercicioId, $desde, $hasta, 'caja', 'P');
        $saldoCajaG = $this->saldoTesoreriaFisica($asientoRepo, $contexto->centroId, $contexto->ejercicioId, $desde, $hasta, 'caja', 'G');
        $saldoFisicoAntes = $saldoCajaP->add($saldoCajaG);

        $crearFisica = new CrearCuentaFisica($ambito, $fisicaRepo, $cuentaRepo);
        $resultado = $crearFisica->ejecutar(['tipo' => 'banco', 'nombre' => 'Banc Sabadell']);
        $sabadellId = $resultado['fisica']->id;
        self::assertNotNull($sabadellId);
        self::assertNotNull($cuentaRepo->tesoreriaDeFisica($contexto->centroId, 'P', $sabadellId));
        self::assertNotNull($cuentaRepo->tesoreriaDeFisica($contexto->centroId, 'G', $sabadellId));

        $realizadoAntes = $asientoRepo->realizadoPorConcepto(
            $contexto->centroId,
            $contexto->ejercicioId,
            'G',
            $desde,
            $hasta,
        );
        $bancoGlobalAntes = (new CalcularSaldos($asientoRepo, $configRepo, $personaRepo, $ambito))->ejecutar(null)['banco'];
        $banco1GAntes = $this->saldoCuentaCodigo($asientoRepo, $contexto, $desde, $hasta, 'G', 'BANCO.1/G');
        $banco2GAntes = $this->saldoCuentaCodigo($asientoRepo, $contexto, $desde, $hasta, 'G', 'BANCO.2/G');

        $crearApunte = new CrearApunte(
            $asientoRepo,
            new PdoConceptoRepository($this->pdo),
            $personaRepo,
            $configRepo,
            $cuentaRepo,
            $fisicaRepo,
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ambito,
            $ejercicioRepo,
        );
        $crearApunte->ejecutar([
            'fecha' => $hasta,
            'cuenta' => 'G',
            'origen' => 'B',
            'concepto_codigo' => '201',
            'cantidad' => '25.00',
            'cuenta_fisica_id' => $sabadellId,
        ]);

        $banco1GDespues = $this->saldoCuentaCodigo($asientoRepo, $contexto, $desde, $hasta, 'G', 'BANCO.1/G');
        self::assertSame($banco1GAntes->toString(), $banco1GDespues->toString(), 'BANCO.1/G no debe moverse');
        $banco2G = $this->saldoCuentaCodigo($asientoRepo, $contexto, $desde, $hasta, 'G', 'BANCO.2/G');
        self::assertSame(
            $banco2GAntes->sub(Dinero::fromInput('25.00'))->toString(),
            $banco2G->toString(),
            'BANCO.2/G debe bajar 25',
        );
        $realizadoDespues = $asientoRepo->realizadoPorConcepto(
            $contexto->centroId,
            $contexto->ejercicioId,
            'G',
            $desde,
            $hasta,
        );
        $delta201 = ($realizadoDespues['201'] ?? 0) - ($realizadoAntes['201'] ?? 0);
        self::assertSame(2500, $delta201, '613 G línea 201 sube 25.00');
        $bancoGlobalDespues = (new CalcularSaldos($asientoRepo, $configRepo, $personaRepo, $ambito))->ejecutar(null)['banco'];
        self::assertSame(
            Dinero::fromInput($bancoGlobalAntes)->sub(Dinero::fromInput('25.00'))->toString(),
            $bancoGlobalDespues,
        );

        $cajaFisica = $fisicaRepo->listarActivasDeCentro($contexto->centroId, 'caja')[0];
        self::assertNotNull($cajaFisica->id);
        $prestamo = new RegistrarPrestamoEntreLibros($asientoRepo, $cuentaRepo, $fisicaRepo, $configRepo, $ambito);
        $pre = $prestamo->ejecutar([
            'cuenta_fisica_id' => $cajaFisica->id,
            'libro_origen' => 'G',
            'libro_destino' => 'P',
            'fecha' => $hasta,
            'cantidad' => '100.00',
        ]);
        self::assertSame('traspaso', $pre['origen']->tipo);
        self::assertSame('traspaso', $pre['destino']->tipo);
        self::assertSame($pre['destino']->id, $pre['origen']->asientoParId);
        self::assertSame($pre['origen']->id, $pre['destino']->asientoParId);

        $saldoCajaPPre = $this->saldoTesoreriaFisica($asientoRepo, $contexto->centroId, $contexto->ejercicioId, $desde, $hasta, 'caja', 'P');
        $saldoCajaGPre = $this->saldoTesoreriaFisica($asientoRepo, $contexto->centroId, $contexto->ejercicioId, $desde, $hasta, 'caja', 'G');
        $saldoFisicoDespuesPrestamo = $saldoCajaPPre->add($saldoCajaGPre);
        self::assertSame($saldoFisicoAntes->toString(), $saldoFisicoDespuesPrestamo->toString(), 'Saldo físico caja invariante tras préstamo');
        self::assertSame(
            $saldoCajaG->sub(Dinero::fromInput('100.00'))->toString(),
            $saldoCajaGPre->toString(),
        );
        self::assertSame(
            $saldoCajaP->add(Dinero::fromInput('100.00'))->toString(),
            $saldoCajaPPre->toString(),
        );

        $bancoFisica = $fisicaRepo->listarActivasDeCentro($contexto->centroId, 'banco');
        $cajaOrden1 = null;
        $bancoOrden1 = null;
        foreach ($fisicaRepo->listarActivasDeCentro($contexto->centroId) as $f) {
            if ($f->tipo === 'caja' && $f->orden === 1) {
                $cajaOrden1 = $f->id;
            }
            if ($f->tipo === 'banco' && $f->orden === 1) {
                $bancoOrden1 = $f->id;
            }
        }
        self::assertNotNull($cajaOrden1);
        self::assertNotNull($bancoOrden1);

        $bancoPPre = $this->saldoCuentaCodigo($asientoRepo, $contexto, $desde, $hasta, 'P', 'BANCO.1/P');
        $traspaso = new RegistrarTraspasoTesoreria($asientoRepo, $cuentaRepo, $fisicaRepo, $configRepo, $ambito);
        $traspaso->ejecutar([
            'libro' => 'P',
            'cuenta_fisica_origen_id' => $cajaOrden1,
            'cuenta_fisica_destino_id' => $bancoOrden1,
            'fecha' => $hasta,
            'cantidad' => '10.00',
        ]);
        $cajaPTr = $this->saldoCuentaCodigo($asientoRepo, $contexto, $desde, $hasta, 'P', 'CAJA.1/P');
        $bancoPTr = $this->saldoCuentaCodigo($asientoRepo, $contexto, $desde, $hasta, 'P', 'BANCO.1/P');
        self::assertSame(
            $saldoCajaPPre->sub(Dinero::fromInput('10.00'))->toString(),
            $cajaPTr->toString(),
        );
        self::assertSame(
            $bancoPPre->add(Dinero::fromInput('10.00'))->toString(),
            $bancoPTr->toString(),
        );

        $desactivar = new DesactivarCuentaFisica($ambito, $fisicaRepo, $cuentaRepo);
        $desactivada = $desactivar->ejecutar($sabadellId);
        self::assertFalse($desactivada->activo);

        $this->expectException(\InvalidArgumentException::class);
        $desactivar->ejecutar($cajaFisica->id);
    }

    private function saldoTesoreriaFisica(
        PdoAsientoRepository $repo,
        int $centroId,
        int $ejercicioId,
        string $desde,
        string $hasta,
        string $tipoFisica,
        string $libro,
    ): Dinero {
        $fisicaRepo = new PdoCuentaFisicaRepository($this->pdo);
        $fisicas = $fisicaRepo->listarActivasDeCentro($centroId, $tipoFisica);
        $fisica = $fisicas[0] ?? null;
        if ($fisica?->id === null) {
            return Dinero::zero();
        }
        foreach ($repo->saldosPorCuenta($centroId, $ejercicioId, $desde, $hasta) as $row) {
            if ($row['tipo'] === 'tesoreria' && $row['cuenta_fisica_id'] === $fisica->id && $row['libro'] === $libro) {
                return Dinero::fromCents($row['saldo_cents']);
            }
        }

        return Dinero::zero();
    }

    private function saldoCuentaCodigo(
        PdoAsientoRepository $repo,
        \src\ambito\domain\value_objects\ContextoActual $contexto,
        string $desde,
        string $hasta,
        string $libro,
        string $codigo,
    ): Dinero {
        foreach ($repo->saldosPorCuenta($contexto->centroId, $contexto->ejercicioId, $desde, $hasta) as $row) {
            if ($row['libro'] === $libro && $row['codigo'] === $codigo) {
                return Dinero::fromCents($row['saldo_cents']);
            }
        }

        return Dinero::zero();
    }
}
