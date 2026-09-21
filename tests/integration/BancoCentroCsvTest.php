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
use src\apuntes\application\AsegurarPendienteBancoCentro;
use src\apuntes\application\CategorizarMovimientoBancoCentro;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\CrearApuntesDeEntrada;
use src\apuntes\application\ImportarCsvBancoCentro;
use src\apuntes\application\ListarApuntes;
use src\apuntes\application\ListarPendientesBancoCentro;
use src\apuntes\application\PreferenciaBancoCentro;
use src\apuntes\domain\services\ContrapartidasGastoGeneral;
use src\apuntes\infrastructure\persistence\PdoBancoCentroImportRepository;
use src\apuntes\infrastructure\persistence\PdoCentroBancoRepository;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\support\ConceptosCentro;

final class BancoCentroCsvTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_banco_centro_csv';

    public function testImportarCentroEsIdempotenteYSePuedeCategorizar(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($pdo))->install();

        $cuentas = new PdoCuentaRepository($pdo);
        $fisicas = new PdoCuentaFisicaRepository($pdo);
        $ejercicios = new PdoEjercicioRepository($pdo);
        $asientos = new PdoAsientoRepository($pdo);
        $filas = new PdoBancoCentroImportRepository($pdo);
        $pendientes = new AsegurarPendienteBancoCentro($cuentas);
        $config = new PdoConfiguracionRepository($pdo);
        $personas = new PdoPersonaRepository($pdo);
        $anio = (int) $config->get()->anio;

        $ambito = new ResolverAmbitoActual($config, new PdoCentroRepository($pdo), $ejercicios);
        $pref = new PreferenciaBancoCentro($ambito, new PdoCentroBancoRepository($pdo));
        $pref->guardar('n26');

        $importar = new ImportarCsvBancoCentro(
            $pdo,
            $ambito,
            $filas,
            $cuentas,
            $fisicas,
            $pendientes,
        );
        $csv = "Date,Payee,Account number,Transaction type,Payment reference,Amount (EUR)\n"
            . $anio . "-03-15,Proveedor luz,ES00,Card,Recibo,-45.00\n"
            . $anio . "-03-16,Donacion,ES00,Income,Ingreso,120.00\n";

        $primero = $importar->ejecutar('n26', null, $csv);
        self::assertSame(2, $primero['nuevos']);
        self::assertSame(0, $primero['repetidos']);
        self::assertSame(2, $primero['pendientes']);

        $segundo = $importar->ejecutar('n26', null, $csv);
        self::assertSame(0, $segundo['nuevos']);
        self::assertSame(2, $segundo['repetidos']);

        $listar = new ListarPendientesBancoCentro($ambito, $filas, $pendientes);
        $pend = $listar->ejecutar();
        self::assertCount(2, $pend);
        foreach ($pend as $fila) {
            self::assertNull($fila['asiento_id']);
        }

        $resolverConceptos = ConceptosCentro::resolver($pdo);
        $crear = new CrearApunte(
            $asientos,
            $resolverConceptos,
            $personas,
            $config,
            $cuentas,
            $fisicas,
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ambito,
            $ejercicios,
        );
        $categorizar = new CategorizarMovimientoBancoCentro(
            $ambito,
            $asientos,
            $cuentas,
            $fisicas,
            $ejercicios,
            $filas,
            new CrearApuntesDeEntrada($crear, $resolverConceptos, $ambito, $personas, new ContrapartidasGastoGeneral()),
            $resolverConceptos,
            new ContrapartidasGastoGeneral(),
            $pendientes,
            $personas,
        );

        $gasto = null;
        foreach ($pend as $fila) {
            if (($fila['sentido'] ?? '') === 'gasto') {
                $gasto = $fila;
                break;
            }
        }
        self::assertNotNull($gasto);
        $categorizar->asignarConceptoG((int) $gasto['fila_id'], '204', null, 'Luz marzo');

        $restantes = $listar->ejecutar();
        self::assertCount(1, $restantes);
        self::assertSame('ingreso', $restantes[0]['sentido']);

        $categorizar->otraContabilidad((int) $restantes[0]['fila_id'], 'Donación externa');
        self::assertSame([], $listar->ejecutar());
        $otras = $listar->otras();
        self::assertCount(1, $otras);
        self::assertSame('ingreso', $otras[0]['sentido']);

        $apuntes = new ListarApuntes(
            $asientos,
            $cuentas,
            $personas,
            new ProyectorAsientoAFilaExcel(),
            $ambito,
        );
        $filasG = $apuntes->ejecutar(['cuenta' => 'G']);
        self::assertCount(1, $filasG);
        self::assertSame('204', $filasG[0]['concepto_codigo']);
    }
}
