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
use src\apuntes\application\BorrarApunte;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\ListarApuntes;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\cierre\application\CerrarEjercicio;
use src\cierre\application\GenerarApertura;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/** Fase 4c (D13): fecha de operación vs imputación. */
final class PeriodificacionTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_periodificacion';

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $this->pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($this->pdo))->install();
    }

    public function testMismaFechaUnSoloAsiento(): void
    {
        $deps = $this->deps();
        $y = $this->anioAbierto($deps);
        $filas = $deps['crear']->ejecutar([
            'fecha' => $y . '-03-15',
            'cuenta' => 'G',
            'origen' => 'C',
            'concepto_codigo' => '201',
            'cantidad' => '10.00',
        ]);
        self::assertCount(1, $filas);
        $asiento = $deps['asientos']->porId($filas[0]->id);
        self::assertNotNull($asiento);
        self::assertNull($asiento->asientoParId);
        self::assertSame('normal', $asiento->tipo);
        self::assertSame($y . '-03-15', $asiento->fecha->format('Y-m-d'));
        self::assertSame($y . '-03-15', $asiento->fechaOperacion()->format('Y-m-d'));
        self::assertSame(1, $this->contarAsientos());
    }

    public function testFechasDistintasMismoEjercicioDosAsientosEnlazados(): void
    {
        $deps = $this->deps();
        $y = $this->anioAbierto($deps);
        $filas = $deps['crear']->ejecutar([
            'fecha' => $y . '-02-08',
            'fecha_imputacion' => $y . '-01-31',
            'cuenta' => 'G',
            'origen' => 'C',
            'concepto_codigo' => '201',
            'cantidad' => '25.00',
        ]);
        self::assertCount(1, $filas);
        self::assertSame($y . '-02-08', $filas[0]->fecha->format('Y-m-d'));
        self::assertSame($y . '-01-31', $filas[0]->fechaImputacion?->format('Y-m-d'));
        self::assertSame('C', $filas[0]->origen);
        self::assertSame('201', $filas[0]->conceptoCodigo);

        self::assertSame(2, $this->contarAsientos());
        $imp = $deps['asientos']->porId($filas[0]->id);
        self::assertNotNull($imp);
        self::assertSame('normal', $imp->tipo);
        self::assertSame($y . '-01-31', $imp->fecha->format('Y-m-d'));
        self::assertNotNull($imp->asientoParId);
        $tes = $deps['asientos']->porId($imp->asientoParId);
        self::assertNotNull($tes);
        self::assertSame('periodificacion', $tes->tipo);
        self::assertSame($y . '-02-08', $tes->fecha->format('Y-m-d'));

        $contexto = $deps['ambito']->ejecutar();
        $realizadoEne = $deps['asientos']->realizadoPorConcepto(
            $contexto->centroId,
            $contexto->ejercicioId,
            'G',
            $y . '-01-01',
            $y . '-01-31',
        );
        $realizadoFeb = $deps['asientos']->realizadoPorConcepto(
            $contexto->centroId,
            $contexto->ejercicioId,
            'G',
            $y . '-02-01',
            $y . '-02-28',
        );
        self::assertSame(2500, $realizadoEne['201'] ?? 0);
        self::assertSame(0, $realizadoFeb['201'] ?? 0);

        $cajaEne = $this->saldoCajaG($deps, $y . '-01-31');
        $cajaFeb = $this->saldoCajaG($deps, $y . '-02-08');
        self::assertSame(0, $cajaEne);
        self::assertSame(-2500, $cajaFeb);

        $listadas = $deps['listar']->ejecutar(['cuenta' => 'G']);
        self::assertCount(1, $listadas);

        (new BorrarApunte($deps['asientos']))->ejecutar($filas[0]->id);
        self::assertSame(0, $this->contarAsientos());
    }

    public function testOrigenAConImputacionDistintaSeRechaza(): void
    {
        $deps = $this->deps();
        $y = $this->anioAbierto($deps);
        $this->expectException(\InvalidArgumentException::class);
        $deps['crear']->ejecutar([
            'fecha' => $y . '-02-08',
            'fecha_imputacion' => $y . '-01-31',
            'cuenta' => 'G',
            'origen' => 'A',
            'concepto_codigo' => '211',
            'cantidad' => '10.00',
        ]);
    }

    public function testOperacionTrasFechaFinSeAparcaYPasaAlEjercicioSiguiente(): void
    {
        $deps = $this->deps();
        $contexto = $deps['ambito']->ejecutar();
        $ej = $deps['ejercicios']->porId($contexto->ejercicioId);
        self::assertNotNull($ej);
        $fechaFin = $ej->fechaFin->format('Y-m-d');
        $fechaOp = $ej->fechaFin->modify('+8 days')->format('Y-m-d');

        $filas = $deps['crear']->ejecutar([
            'fecha' => $fechaOp,
            'fecha_imputacion' => $fechaFin,
            'cuenta' => 'G',
            'origen' => 'C',
            'concepto_codigo' => '201',
            'cantidad' => '10.00',
        ]);
        self::assertCount(1, $filas);
        self::assertSame(2, $this->contarAsientos());

        $cajaAlCierre = $this->saldoCajaG($deps, $fechaFin);
        $puenteAlCierre = $this->saldoPuentePeriodificacionG($deps, $fechaFin);
        self::assertSame(0, $cajaAlCierre);
        self::assertSame(-1000, $puenteAlCierre);

        $cerrar = new CerrarEjercicio($deps['ejercicios']);
        $cerrar->ejecutar($contexto->ejercicioId);

        $inicioSiguiente = $ej->fechaFin->modify('+1 day')->format('Y-m-d');
        $finSiguiente = $ej->fechaFin->modify('+1 year')->format('Y-m-d');
        $crearEjercicio = new CrearEjercicio(
            $deps['ejercicios'],
            $deps['generarApertura'],
            new SincronizarConfiguracionConEjercicio($deps['config']),
        );
        $siguiente = $crearEjercicio->ejecutar([
            'centro_id' => $contexto->centroId,
            'etiqueta' => 'siguiente',
            'fecha_inicio' => $inicioSiguiente,
            'fecha_fin' => $finSiguiente,
        ]);
        self::assertNotNull($siguiente->id);

        $periodificacionEnAnterior = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM asientos WHERE ejercicio_id = {$contexto->ejercicioId} AND tipo = 'periodificacion'"
        )->fetchColumn();
        $periodificacionEnSiguiente = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM asientos WHERE ejercicio_id = {$siguiente->id} AND tipo = 'periodificacion'"
        )->fetchColumn();
        self::assertSame(0, $periodificacionEnAnterior);
        self::assertSame(1, $periodificacionEnSiguiente);

        $cajaApertura = $this->saldoCajaGHasta($deps, $siguiente->id, $inicioSiguiente);
        $puenteApertura = $this->saldoPuenteHasta($deps, $siguiente->id, $inicioSiguiente);
        self::assertSame(0, $cajaApertura);
        self::assertSame(-1000, $puenteApertura);

        $cajaTrasPago = $this->saldoCajaGHasta($deps, $siguiente->id, $fechaOp);
        $puenteTrasPago = $this->saldoPuenteHasta($deps, $siguiente->id, $fechaOp);
        self::assertSame(-1000, $cajaTrasPago);
        self::assertSame(0, $puenteTrasPago);
    }

    /** @return array<string, mixed> */
    private function deps(): array
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $fisicas = new PdoCuentaFisicaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, new PdoCentroRepository($this->pdo), $ejercicios);
        $generarApertura = new GenerarApertura($ejercicios, $asientos, $cuentas);
        $proyector = new ProyectorAsientoAFilaExcel();
        $crear = new CrearApunte(
            $asientos,
            new PdoConceptoRepository($this->pdo),
            $personas,
            $config,
            $cuentas,
            $fisicas,
            new TraductorApuntesAAsientos(),
            $proyector,
            $ambito,
            $ejercicios,
            $generarApertura,
        );
        $listar = new ListarApuntes($asientos, $cuentas, $personas, $proyector, $ambito);

        return compact(
            'config',
            'asientos',
            'cuentas',
            'ejercicios',
            'ambito',
            'crear',
            'listar',
            'generarApertura',
        );
    }

    /** @param array<string, mixed> $deps */
    private function anioAbierto(array $deps): string
    {
        $contexto = $deps['ambito']->ejecutar();
        $ej = $deps['ejercicios']->porId($contexto->ejercicioId);
        self::assertNotNull($ej);

        return $ej->fechaInicio->format('Y');
    }

    private function contarAsientos(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM asientos')->fetchColumn();
    }

    /** @param array<string, mixed> $deps */
    private function saldoCajaG(array $deps, string $hasta): int
    {
        $contexto = $deps['ambito']->ejecutar();

        return $this->saldoCajaGHasta($deps, $contexto->ejercicioId, $hasta);
    }

    /** @param array<string, mixed> $deps */
    private function saldoCajaGHasta(array $deps, int $ejercicioId, string $hasta): int
    {
        $contexto = $deps['ambito']->ejecutar();
        foreach ($deps['asientos']->saldosPorCuenta($contexto->centroId, $ejercicioId, null, $hasta, 'G') as $row) {
            if ($row['codigo'] === 'CAJA.1/G') {
                return (int) $row['saldo_cents'];
            }
        }

        return 0;
    }

    /** @param array<string, mixed> $deps */
    private function saldoPuentePeriodificacionG(array $deps, string $hasta): int
    {
        $contexto = $deps['ambito']->ejecutar();

        return $this->saldoPuenteHasta($deps, $contexto->ejercicioId, $hasta);
    }

    /** @param array<string, mixed> $deps */
    private function saldoPuenteHasta(array $deps, int $ejercicioId, string $hasta): int
    {
        $contexto = $deps['ambito']->ejecutar();
        foreach ($deps['asientos']->saldosPorCuenta($contexto->centroId, $ejercicioId, null, $hasta, 'G') as $row) {
            if ($row['codigo'] === 'PUENTE.PERIODIFICACION') {
                return (int) $row['saldo_cents'];
            }
        }

        return 0;
    }
}
