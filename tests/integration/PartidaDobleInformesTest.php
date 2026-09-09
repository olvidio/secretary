<?php

declare(strict_types=1);

namespace Tests\integration;

use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\application\CrearApunte;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\asientos\domain\exceptions\AsientoDescuadrado;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\informes\application\CalcularSaldos;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/** Fase 3 OLA 2: informes y entrada sobre asientos. */
final class PartidaDobleInformesTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_partida_ola2';

    public function testGastoGeneralConInicialesNoAlteraSaldoPersonal(): void
    {
        $ctx = $this->importarYPreparar();
        $saldosAntes = (new CalcularSaldos(
            $ctx['asientos'],
            $ctx['config'],
            $ctx['personas'],
            $ctx['ambito'],
        ))->ejecutar(null);

        $acAntes = $this->saldoPersona($saldosAntes, 'ac');

        (new CrearApunte(
            $ctx['asientos'],
            $ctx['conceptos'],
            $ctx['personas'],
            $ctx['config'],
            $ctx['cuentas'],
            $ctx['fisicas'],
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ctx['ambito'],
            $ctx['ejercicioRepo'],
        ))->ejecutar([
            'fecha' => '2026-03-15',
            'cuenta' => 'G',
            'origen' => 'A',
            'iniciales' => 'ac',
            'concepto_codigo' => '211',
            'cantidad' => '100.00',
        ]);

        $saldosDespues = (new CalcularSaldos(
            $ctx['asientos'],
            $ctx['config'],
            $ctx['personas'],
            $ctx['ambito'],
        ))->ejecutar(null);

        self::assertSame($acAntes, $this->saldoPersona($saldosDespues, 'ac'));
    }

    public function testCrearIngresoPOrigenCCreaAsientoCuadradoYProyeccion(): void
    {
        $ctx = $this->importarYPreparar();
        $filas = (new CrearApunte(
            $ctx['asientos'],
            $ctx['conceptos'],
            $ctx['personas'],
            $ctx['config'],
            $ctx['cuentas'],
            $ctx['fisicas'],
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ctx['ambito'],
            $ctx['ejercicioRepo'],
        ))->ejecutar([
            'fecha' => '2026-03-01',
            'cuenta' => 'P',
            'origen' => 'C',
            'iniciales' => 'ac',
            'concepto_codigo' => '111',
            'cantidad' => '50.00',
        ]);

        self::assertCount(1, $filas);
        $fila = $filas[0]->toArray();
        self::assertSame('C', $fila['origen']);
        self::assertSame('111', $fila['concepto_codigo']);
        self::assertSame('50.00', $fila['cantidad']);

        $asiento = $ctx['asientos']->porId($fila['id']);
        self::assertNotNull($asiento);
        $asiento->assertCuadre();
    }

    public function testAsientoDescuadradoNoSeGuarda(): void
    {
        $ctx = $this->importarYPreparar();
        $amb = $ctx['ambito']->ejecutar();
        $caja = $ctx['cuentas']->tesoreria($amb->centroId, 'P', 'CAJA');
        $ingreso = $ctx['cuentas']->buscar($amb->centroId, null, 'P', '111');
        self::assertNotNull($caja?->id);
        self::assertNotNull($ingreso?->id);

        $this->expectException(AsientoDescuadrado::class);
        new Asiento(
            null,
            $amb->ejercicioId,
            'P',
            null,
            new \DateTimeImmutable('2026-03-01'),
            'test',
            'normal',
            'manual',
            null,
            [
                new Movimiento(null, 1, $caja->id, null, 5000, 0),
                new Movimiento(null, 2, $ingreso->id, null, 0, 4000),
            ],
            '111',
        );
    }

    /** @param array<string, mixed> $saldos */
    private function saldoPersona(array $saldos, string $iniciales): string
    {
        foreach ($saldos['por_persona'] as $p) {
            if ($p['iniciales'] === $iniciales) {
                return $p['saldo_a'];
            }
        }

        return '0.00';
    }

    /** @return array<string, mixed> */
    private function importarYPreparar(): array
    {
        $this->saltarSiNoHayPgsql();
        $excelPath = dirname(__DIR__, 2) . '/moviments2026.xlsm';
        if (!is_readable($excelPath)) {
            self::markTestSkipped('Falta moviments2026.xlsm');
        }

        try {
            $pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        } catch (PDOException $e) {
            self::markTestSkipped($e->getMessage());
        }

        (new SchemaInstaller($pdo))->install();
        $config = new PdoConfiguracionRepository($pdo);
        $personas = new PdoPersonaRepository($pdo);
        $conceptos = new PdoConceptoRepository($pdo);
        $apuntes = new \src\apuntes\infrastructure\persistence\PdoApunteRepository($pdo);
        $presupuesto = new PdoPresupuestoRepository($pdo);
        $asientos = new PdoAsientoRepository($pdo);
        $cuentas = new PdoCuentaRepository($pdo);
        $fisicas = new PdoCuentaFisicaRepository($pdo);
        $centroRepo = new PdoCentroRepository($pdo);
        $ejercicioRepo = new PdoEjercicioRepository($pdo);
        $ambito = new ResolverAmbitoActual($config, $centroRepo, $ejercicioRepo);

        (new ImportarExcelSecretario($pdo, $config, $personas, $conceptos, $apuntes, $presupuesto))
            ->ejecutar($excelPath, true);

        return compact('pdo', 'config', 'personas', 'conceptos', 'apuntes', 'presupuesto', 'asientos', 'cuentas', 'fisicas', 'ambito', 'ejercicioRepo');
    }
}
