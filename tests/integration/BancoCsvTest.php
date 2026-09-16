<?php

declare(strict_types=1);

namespace Tests\integration;

use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\entity\Identidad;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\personal\application\AsegurarPlanPersonal;
use src\personal\application\CategorizarMovimientoBanco;
use src\personal\application\ImportarCsvBanco;
use src\personal\application\ListarPendientesBanco;
use src\personal\application\PreferenciaBancoPersonal;
use src\personal\application\ResolverPersonaActual;
use src\personal\domain\services\ResolverCategoriaPlantillaPersonal;
use src\apuntes\infrastructure\persistence\PdoPlantillaApunteRepository;
use src\personal\infrastructure\persistence\PdoPersonalBancoRepository;
use src\personal\infrastructure\persistence\PdoBancoImportRepository;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class BancoCsvTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_banco_csv';

    public function testImportarN26EsIdempotenteYSePuedeCategorizar(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($pdo))->install();

        $personas = new PdoPersonaRepository($pdo);
        $identidades = new PdoIdentidadRepository($pdo);
        $cuentas = new PdoCuentaRepository($pdo);
        $ejercicios = new PdoEjercicioRepository($pdo);
        $asientos = new PdoAsientoRepository($pdo);
        $filas = new PdoBancoImportRepository($pdo);
        $plan = new AsegurarPlanPersonal($cuentas);
        $config = new PdoConfiguracionRepository($pdo);
        $anio = (int) $config->get()->anio;
        $centros = $pdo->query('SELECT id FROM centros ORDER BY id LIMIT 1')->fetchColumn();
        self::assertNotFalse($centros);
        $centroId = (int) $centros;

        $persona = $personas->guardar(new Persona(
            null,
            'Nerea',
            'Ene',
            'ne',
            null,
            null,
            null,
            null,
            null,
            1,
            $centroId,
        ));
        self::assertNotNull($persona->id);
        $id = $identidades->guardar(new Identidad(
            null,
            'nerea@example.test',
            password_hash('clave', PASSWORD_DEFAULT),
            'Nerea',
            true,
            0,
            null,
            null,
            'nerea',
        ));
        self::assertNotNull($id->id);
        $identidades->vincularPersona($id->id, $persona->id);

        $resolver = new ResolverPersonaActual(
            $identidades,
            $personas,
            $ejercicios,
            $plan,
            $id->id,
            $persona->id,
        );
        $prefBanco = new PreferenciaBancoPersonal($resolver, new PdoPersonalBancoRepository($pdo));
        $prefBanco->guardar('caixabank');
        self::assertSame('caixabank', $prefBanco->leer());
        $categorizar = new CategorizarMovimientoBanco(
            $resolver,
            $asientos,
            $cuentas,
            new ResolverCategoriaPlantillaPersonal(new PdoPlantillaApunteRepository($pdo), $cuentas),
        );
        $importar = new ImportarCsvBanco(
            $pdo,
            $resolver,
            $filas,
            $asientos,
            $cuentas,
            $ejercicios,
            $plan,
        );
        $csv = "Date,Payee,Account number,Transaction type,Payment reference,Amount (EUR)\n"
            . $anio . "-03-15,Mercadona,DE00,Card,Compra,-12.50\n"
            . $anio . "-03-16,Empresa,DE00,Income,Nomina,200.00\n";

        $primero = $importar->ejecutar('n26', $csv);
        self::assertSame(2, $primero['nuevos']);
        self::assertSame(0, $primero['repetidos']);
        self::assertSame(2, $primero['pendientes']);

        $segundo = $importar->ejecutar('n26', $csv);
        self::assertSame(0, $segundo['nuevos']);
        self::assertSame(2, $segundo['repetidos']);

        $pend = (new ListarPendientesBanco($resolver, $filas, $plan))->ejecutar();
        self::assertCount(2, $pend);
        $gasto = null;
        foreach ($pend as $p) {
            if ($p['sentido'] === 'gasto') {
                $gasto = $p;
            }
        }
        self::assertNotNull($gasto);
        $cat22 = $cuentas->buscar($centroId, $persona->id, 'X', '22');
        self::assertNotNull($cat22?->id);
        $categorizar
            ->ejecutar((int) $gasto['asiento_id'], $cat22->id, 'Compra del sábado');
        $asientoGasto = $asientos->porId((int) $gasto['asiento_id']);
        self::assertNotNull($asientoGasto);
        self::assertSame('Compra del sábado', $asientoGasto->glosa);
        $despues = (new ListarPendientesBanco($resolver, $filas, $plan))->ejecutar();
        self::assertCount(1, $despues);
        $ingresoPend = $despues[0];
        self::assertSame('ingreso', $ingresoPend['sentido']);
        $otraIng = $cuentas->buscar($centroId, $persona->id, 'X', AsegurarPlanPersonal::CODIGO_OTRA_INGRESO);
        self::assertNotNull($otraIng?->id);
        $listar = new ListarPendientesBanco($resolver, $filas, $plan);
        $categorizar
            ->ejecutar((int) $ingresoPend['asiento_id'], $otraIng->id);
        self::assertCount(0, $listar->ejecutar());
        $enOtra = $listar->otras();
        self::assertCount(1, $enOtra);
        $cat111 = $cuentas->buscar($centroId, $persona->id, 'X', '111');
        self::assertNotNull($cat111?->id);
        $categorizar
            ->ejecutar((int) $enOtra[0]['asiento_id'], $cat111->id);
        self::assertCount(0, $listar->otras());

        $csvCaprabo = "Date,Payee,Account number,Transaction type,Payment reference,Amount (EUR)\n"
            . $anio . "-08-09,CAPRABO 7776,DE00,Card,,-16.04\n"
            . $anio . "-08-17,CAPRABO 7851,DE00,Card,,-23.91\n";
        $importar->ejecutar('n26', $csvCaprabo);
        $cap = $listar->ejecutar();
        self::assertCount(2, $cap);
        $categorizar
            ->ejecutar((int) $cap[0]['asiento_id'], $cat22->id);
        $resto = $listar->ejecutar();
        self::assertCount(1, $resto);
        self::assertSame($cat22->id, $resto[0]['sugerida_id']);
        self::assertSame('gasto', $resto[0]['sentido']);

        $categorizar
            ->ejecutar((int) $resto[0]['asiento_id'], $cat22->id);
        $csvCajero = "Date,Payee,Account number,Transaction type,Payment reference,Amount (EUR)\n"
            . $anio . "-09-01,CAJERO,DE00,ATM,,-50.00\n";
        $importar->ejecutar('n26', $csvCajero);
        $cajero = $listar->ejecutar();
        self::assertCount(1, $cajero);
        $categorizar
            ->traspasoACaja((int) $cajero[0]['asiento_id'], 'Sacar efectivo');
        self::assertCount(0, $listar->ejecutar());
        $asientoTraspaso = $asientos->porId((int) $cajero[0]['asiento_id']);
        self::assertNotNull($asientoTraspaso);
        self::assertSame('traspaso', $asientoTraspaso->tipo);
        self::assertSame('Sacar efectivo', $asientoTraspaso->glosa);
        $caja = $cuentas->buscar($centroId, $persona->id, 'X', 'CAJA');
        $banco = $cuentas->buscar($centroId, $persona->id, 'X', 'BANCO');
        self::assertNotNull($caja?->id);
        self::assertNotNull($banco?->id);
        $porCuenta = [];
        foreach ($asientoTraspaso->movimientos as $mov) {
            $porCuenta[$mov->cuentaId] = $mov;
        }
        self::assertSame(5000, $porCuenta[$caja->id]->debeCents - $porCuenta[$caja->id]->haberCents);
        self::assertSame(-5000, $porCuenta[$banco->id]->debeCents - $porCuenta[$banco->id]->haberCents);
    }
}
