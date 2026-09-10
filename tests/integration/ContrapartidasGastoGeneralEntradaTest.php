<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\CrearApuntesDeEntrada;
use src\apuntes\application\ListarApuntes;
use src\apuntes\domain\services\ContrapartidasGastoGeneral;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\cierre\application\GenerarApertura;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class ContrapartidasGastoGeneralEntradaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_contrapartidas_g';

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

    public function testGastoGConInicialesCrea11121Y11(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $conceptos = new PdoConceptoRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, new PdoCentroRepository($this->pdo), $ejercicios);
        $centroId = $ambito->ejecutar()->centroId;
        $persona = new Persona(null, 'José R.', 'JRM', 'jrm', null, null, null, null, null, 1, $centroId);
        $personas->guardar($persona);
        $persona = $personas->porIniciales('jrm');
        self::assertNotNull($persona);
        (new AsegurarCuentaCorrientePersona($cuentas))->ejecutar($persona);

        $crear = new CrearApuntesDeEntrada(
            new CrearApunte(
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
            ),
            $conceptos,
            $personas,
            new ContrapartidasGastoGeneral(),
        );
        $y = $ejercicios->porId($ambito->ejecutar()->ejercicioId)?->fechaInicio->format('Y');
        self::assertNotNull($y);

        $filas = $crear->ejecutar([
            'contrapartidas' => true,
            'fecha' => $y . '-03-15',
            'cuenta' => 'G',
            'origen' => 'A',
            'iniciales' => 'jrm',
            'concepto_codigo' => '211',
            'observaciones' => 'club',
            'cantidad' => '40.00',
        ]);

        $codigos = array_map(static fn ($f) => $f->cuenta . '/' . $f->conceptoCodigo, $filas);
        self::assertSame(['P/111', 'P/21', 'G/11', 'G/211'], $codigos);
        foreach ($filas as $fila) {
            self::assertSame('40.00', $fila->cantidad->toString());
            self::assertSame('jrm', $fila->iniciales);
        }

        $listados = (new ListarApuntes(
            $asientos,
            $cuentas,
            $personas,
            new ProyectorAsientoAFilaExcel(),
            $ambito,
        ))->ejecutar(['iniciales' => 'jrm']);
        $vistos = array_map(static fn (array $a): string => $a['cuenta'] . '/' . $a['concepto_codigo'], $listados);
        sort($vistos);
        self::assertSame(['G/11', 'G/211', 'P/111', 'P/21'], $vistos);
    }
}
