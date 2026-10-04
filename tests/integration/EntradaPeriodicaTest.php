<?php

declare(strict_types=1);

namespace Tests\integration;

use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\entity\Centro;
use src\ambito\domain\entity\Ejercicio;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\ambito\infrastructure\persistence\PdoPobladorCentro;
use src\apuntes\application\ComprobarAccesoCentroSg;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\CrearApuntesDeEntrada;
use src\apuntes\application\EjecutarEntradasPeriodicas;
use src\apuntes\application\GuardarEntradaPeriodica;
use src\apuntes\application\ListarPendientesEntradaPeriodica;
use src\apuntes\domain\services\CalculadorVencimientosEntradaPeriodica;
use src\apuntes\domain\services\ContrapartidasGastoGeneral;
use src\apuntes\application\GuardarPlantillaApunte;
use src\apuntes\domain\value_objects\ReferenciaPlantillaEnConcepto;
use src\apuntes\infrastructure\persistence\PdoEntradaPeriodicaRepository;
use src\apuntes\infrastructure\persistence\PdoPlantillaApunteRepository;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\cierre\application\GenerarApertura;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;
use Tests\support\ConceptosCentro;

final class EntradaPeriodicaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_entrada_periodica';

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

    public function testGuardarListarPendientesYEjecutar(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);

        $centro = $centros->guardar(new Centro(
            null,
            'SG-PER',
            'Centro sg periódicas',
            'sg',
            'vivienda',
            CatalogoPlanesContables::CENTRO_SG,
        ));
        self::assertNotNull($centro->id);
        $ejercicios->guardar(new Ejercicio(
            null,
            $centro->id,
            '2026',
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-12-31'),
            new DateTimeImmutable('2026-01-01'),
        ));
        (new PdoPobladorCentro($this->pdo))->ejecutar($centro->id);
        $personas->guardar(new Persona(null, 'Ana', 'Test', 'AT', null, null, null, null, null, 1, $centro->id));

        $ambito = new ResolverAmbitoActual($config, $centros, $ejercicios, $centro->id);
        $conceptos = ConceptosCentro::resolver($this->pdo);
        $repo = new PdoEntradaPeriodicaRepository($this->pdo);
        $plantillasRepo = new PdoPlantillaApunteRepository($this->pdo);
        $centroSg = new ComprobarAccesoCentroSg($ambito, $centros);

        $guardar = new GuardarEntradaPeriodica($repo, $ambito, $centroSg, $conceptos, $personas, $plantillasRepo);
        $entrada = $guardar->ejecutar([
            'iniciales' => 'AT',
            'concepto_codigo' => '21',
            'observaciones' => 'Cuota',
            'cantidad' => '25,00',
            'periodicidad' => 'mensual',
            'fecha_ancla' => '2026-01-05',
        ]);
        self::assertSame('mensual', $entrada['periodicidad']);

        $listarPend = new ListarPendientesEntradaPeriodica(
            $repo,
            $ambito,
            $centroSg,
            new CalculadorVencimientosEntradaPeriodica(),
            $conceptos,
            $plantillasRepo,
        );
        self::assertCount(3, $listarPend->ejecutar('2026-03-10'));

        $crearApunte = new CrearApunte(
            $asientos,
            $conceptos,
            $personas,
            $config,
            $cuentas,
            new PdoCuentaFisicaRepository($this->pdo),
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ambito,
            $ejercicios,
            new GenerarApertura($ejercicios, $asientos, $cuentas),
            $centros,
        );
        $crearEntrada = new CrearApuntesDeEntrada(
            $crearApunte,
            $conceptos,
            $ambito,
            $personas,
            new ContrapartidasGastoGeneral(),
            $centros,
        );
        $ejecutar = new EjecutarEntradasPeriodicas(
            $repo,
            $ambito,
            $centroSg,
            new CalculadorVencimientosEntradaPeriodica(),
            $crearEntrada,
            $plantillasRepo,
        );
        $res = $ejecutar->ejecutar([
            'lineas' => [
                ['entrada_id' => $entrada['id'], 'fecha' => '2026-01-05'],
                ['entrada_id' => $entrada['id'], 'fecha' => '2026-02-05'],
            ],
        ]);
        self::assertSame(2, $res['ejecutados']);
        $pend2 = $listarPend->ejecutar('2026-03-10');
        self::assertCount(1, $pend2);
        self::assertSame('2026-03-05', $pend2[0]['fecha']);
    }

    public function testEntradaPeriodicaConPlantillaEjecutaTodosLosMovimientos(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);

        $centro = $centros->guardar(new Centro(
            null,
            'SG-PLT',
            'Centro sg plantilla periódica',
            'sg',
            'vivienda',
            CatalogoPlanesContables::CENTRO_SG,
        ));
        self::assertNotNull($centro->id);
        $ejercicios->guardar(new Ejercicio(
            null,
            $centro->id,
            '2026',
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-12-31'),
            new DateTimeImmutable('2026-01-01'),
        ));
        (new PdoPobladorCentro($this->pdo))->ejecutar($centro->id);
        $personas->guardar(new Persona(null, 'Ana', 'Test', 'AT', null, null, null, null, null, 1, $centro->id));

        $ambito = new ResolverAmbitoActual($config, $centros, $ejercicios, $centro->id);
        $conceptos = ConceptosCentro::resolver($this->pdo);
        $repo = new PdoEntradaPeriodicaRepository($this->pdo);
        $plantillasRepo = new PdoPlantillaApunteRepository($this->pdo);
        $centroSg = new ComprobarAccesoCentroSg($ambito, $centros);

        $plantilla = (new GuardarPlantillaApunte($plantillasRepo, $conceptos, $ambito))->ejecutar([
            'cuenta' => 'G',
            'nombre' => 'Doble sg',
            'lineas' => [
                ['cuenta' => 'G', 'origen' => 'C', 'concepto_codigo' => '11', 'observaciones' => ''],
                ['cuenta' => 'G', 'origen' => 'C', 'concepto_codigo' => '21', 'observaciones' => 'luz'],
            ],
        ]);
        self::assertNotNull($plantilla['id']);

        $guardar = new GuardarEntradaPeriodica($repo, $ambito, $centroSg, $conceptos, $personas, $plantillasRepo);
        $entrada = $guardar->ejecutar([
            'iniciales' => 'AT',
            'concepto_codigo' => ReferenciaPlantillaEnConcepto::codigo((int) $plantilla['id']),
            'observaciones' => '',
            'cantidad' => '10,00',
            'periodicidad' => 'mensual',
            'fecha_ancla' => '2026-02-10',
        ]);

        $listarPend = new ListarPendientesEntradaPeriodica(
            $repo,
            $ambito,
            $centroSg,
            new CalculadorVencimientosEntradaPeriodica(),
            $conceptos,
            $plantillasRepo,
        );
        $pend = $listarPend->ejecutar('2026-02-10');
        self::assertCount(1, $pend);
        self::assertSame('Doble sg', $pend[0]['concepto_etiqueta']);

        $crearApunte = new CrearApunte(
            $asientos,
            $conceptos,
            $personas,
            $config,
            $cuentas,
            new PdoCuentaFisicaRepository($this->pdo),
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ambito,
            $ejercicios,
            new GenerarApertura($ejercicios, $asientos, $cuentas),
            $centros,
        );
        $crearEntrada = new CrearApuntesDeEntrada(
            $crearApunte,
            $conceptos,
            $ambito,
            $personas,
            new ContrapartidasGastoGeneral(),
            $centros,
        );
        $ejecutar = new EjecutarEntradasPeriodicas(
            $repo,
            $ambito,
            $centroSg,
            new CalculadorVencimientosEntradaPeriodica(),
            $crearEntrada,
            $plantillasRepo,
        );
        $res = $ejecutar->ejecutar([
            'lineas' => [
                ['entrada_id' => $entrada['id'], 'fecha' => '2026-02-10'],
            ],
        ]);
        self::assertSame(1, $res['ejecutados']);
        self::assertCount(2, $res['apuntes']);
        self::assertSame(['11', '21'], array_column($res['apuntes'], 'concepto_codigo'));
    }
}
