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
use src\apuntes\application\ActualizarApunte;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\ListarApuntes;
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

final class ActualizarApunteTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_actualizar_apunte';

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

    public function testCambiaObservacionesYConservaElResto(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, new PdoCentroRepository($this->pdo), $ejercicios);
        $centroId = $ambito->ejecutar()->centroId;
        $personas->guardar(new Persona(null, 'José R.', 'JRM', 'jrm', null, null, null, null, null, 1, $centroId));
        $persona = $personas->porIniciales('jrm');
        self::assertNotNull($persona);
        (new AsegurarCuentaCorrientePersona($cuentas))->ejecutar($persona);

        $crear = new CrearApunte(
            $asientos,
            new PdoConceptoRepository($this->pdo),
            $personas,
            $config,
            $cuentas,
            new PdoCuentaFisicaRepository($this->pdo),
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ambito,
            $ejercicios,
            new GenerarApertura($ejercicios, $asientos, $cuentas),
        );
        $y = $ejercicios->porId($ambito->ejecutar()->ejercicioId)?->fechaInicio->format('Y');
        self::assertNotNull($y);
        $filas = $crear->ejecutar([
            'fecha' => $y . '-03-15',
            'cuenta' => 'P',
            'origen' => 'A',
            'iniciales' => 'jrm',
            'concepto_codigo' => '22',
            'observaciones' => 'comida',
            'cantidad' => '12.50',
        ]);
        self::assertCount(1, $filas);
        $id = $filas[0]->id;

        $nuevas = (new ActualizarApunte($asientos, $crear, $this->pdo))->ejecutar($id, [
            'fecha' => $y . '-03-15',
            'cuenta' => 'P',
            'origen' => 'A',
            'iniciales' => 'jrm',
            'concepto_codigo' => '22',
            'observaciones' => 'super',
            'cantidad' => '12.50',
        ]);
        self::assertCount(1, $nuevas);
        self::assertSame('super', $nuevas[0]->observaciones);
        self::assertSame('22', $nuevas[0]->conceptoCodigo);
        self::assertSame('12.50', $nuevas[0]->cantidad->toString());

        $listados = (new ListarApuntes(
            $asientos,
            $cuentas,
            $personas,
            new ProyectorAsientoAFilaExcel(),
            $ambito,
        ))->ejecutar(['iniciales' => 'jrm']);
        self::assertCount(1, $listados);
        self::assertSame('super', $listados[0]['observaciones']);
        self::assertSame($asientos->porId($id), null);
    }

    public function testRechazaSiElConceptoNoExisteYNoBorraElOriginal(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, new PdoCentroRepository($this->pdo), $ejercicios);
        $centroId = $ambito->ejecutar()->centroId;
        $personas->guardar(new Persona(null, 'José R.', 'JRM', 'jrm', null, null, null, null, null, 1, $centroId));
        $persona = $personas->porIniciales('jrm');
        self::assertNotNull($persona);
        (new AsegurarCuentaCorrientePersona($cuentas))->ejecutar($persona);

        $crear = new CrearApunte(
            $asientos,
            new PdoConceptoRepository($this->pdo),
            $personas,
            $config,
            $cuentas,
            new PdoCuentaFisicaRepository($this->pdo),
            new TraductorApuntesAAsientos(),
            new ProyectorAsientoAFilaExcel(),
            $ambito,
            $ejercicios,
            new GenerarApertura($ejercicios, $asientos, $cuentas),
        );
        $y = $ejercicios->porId($ambito->ejecutar()->ejercicioId)?->fechaInicio->format('Y');
        self::assertNotNull($y);
        $filas = $crear->ejecutar([
            'fecha' => $y . '-03-15',
            'cuenta' => 'P',
            'origen' => 'A',
            'iniciales' => 'jrm',
            'concepto_codigo' => '22',
            'cantidad' => '5.00',
        ]);
        $id = $filas[0]->id;

        try {
            (new ActualizarApunte($asientos, $crear, $this->pdo))->ejecutar($id, [
                'fecha' => $y . '-03-15',
                'cuenta' => 'P',
                'origen' => 'A',
                'iniciales' => 'jrm',
                'concepto_codigo' => 'no-existe',
                'cantidad' => '5.00',
            ]);
            self::fail('Debía rechazar el concepto');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Concepto', $e->getMessage());
        }
        self::assertNotNull($asientos->porId($id));
    }
}
