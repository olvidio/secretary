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
use src\apuntes\application\CalcularCuadreApuntesA;
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

final class CalcularCuadreApuntesATest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_cuadre_a';

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

    public function testSugiere111SiAntesCuadrabaYSoloGastosEnFecha(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, new PdoCentroRepository($this->pdo), $ejercicios);
        $centroId = $ambito->ejecutar()->centroId;
        $persona = new Persona(null, 'José R.', 'JRM', 'jrm', null, null, null, null, null, 1, $centroId);
        $personas->guardar($persona);
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

        $crear->ejecutar([
            'fecha' => $y . '-03-14',
            'cuenta' => 'P',
            'origen' => 'A',
            'iniciales' => 'jrm',
            'concepto_codigo' => '22',
            'observaciones' => 'Comida',
            'cantidad' => '100.00',
        ]);
        $crear->ejecutar([
            'fecha' => $y . '-03-14',
            'cuenta' => 'P',
            'origen' => 'A',
            'iniciales' => 'jrm',
            'concepto_codigo' => '111',
            'observaciones' => 'Trabajo',
            'cantidad' => '100.00',
        ]);
        $crear->ejecutar([
            'fecha' => $y . '-03-15',
            'cuenta' => 'P',
            'origen' => 'A',
            'iniciales' => 'jrm',
            'concepto_codigo' => '22',
            'observaciones' => 'Super',
            'cantidad' => '50.00',
        ]);

        $cuadre = new CalcularCuadreApuntesA(
            new ListarApuntes(
                $asientos,
                $cuentas,
                $personas,
                new ProyectorAsientoAFilaExcel(),
                $ambito,
            ),
            new PdoConceptoRepository($this->pdo),
        );
        $r = $cuadre->ejecutar('P', 'jrm', $y . '-03-15');

        self::assertTrue($r['aplica']);
        self::assertFalse($r['cuadrado']);
        self::assertTrue($r['cuadrado_antes_fecha']);
        self::assertTrue($r['solo_gastos_fecha']);
        self::assertSame('50.00', $r['saldo_fecha']);
        self::assertSame('111', $r['sugerencia']['concepto_codigo']);
        self::assertSame('50.00', $r['sugerencia']['cantidad']);
    }
}
