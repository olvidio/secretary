<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\application\BuscarSugerenciasObservacion;
use src\apuntes\application\CrearApunte;
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

final class SugerenciasObservacionTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_sugerencias_obs';

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

    public function testSugierePorInicialesYOrdenaPorFrecuencia(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, new PdoCentroRepository($this->pdo), $ejercicios);
        $centroId = $ambito->ejecutar()->centroId;
        $personas->guardar(new Persona(null, 'Ana', 'A', 'aa', null, null, null, null, null, 1, $centroId));
        $personas->guardar(new Persona(null, 'Bea', 'B', 'bb', null, null, null, null, null, 2, $centroId));

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

        foreach (['03-10', '03-11', '03-12'] as $dia) {
            $crear->ejecutar([
                'fecha' => $y . '-' . $dia,
                'cuenta' => 'P',
                'origen' => 'C',
                'iniciales' => 'aa',
                'concepto_codigo' => '111',
                'observaciones' => 'Iberdrola',
                'cantidad' => '10.00',
            ]);
        }
        $crear->ejecutar([
            'fecha' => $y . '-04-01',
            'cuenta' => 'P',
            'origen' => 'C',
            'iniciales' => 'aa',
            'concepto_codigo' => '112',
            'observaciones' => 'Iberdrola cocina',
            'cantidad' => '5.00',
        ]);
        $crear->ejecutar([
            'fecha' => $y . '-04-02',
            'cuenta' => 'P',
            'origen' => 'C',
            'iniciales' => 'bb',
            'concepto_codigo' => '111',
            'observaciones' => 'Iberdrola',
            'cantidad' => '5.00',
        ]);

        $buscar = new BuscarSugerenciasObservacion($asientos, $ambito);
        $hits = $buscar->ejecutar('iberdrola', 'P', 'aa');
        self::assertCount(2, $hits);
        self::assertSame('Iberdrola', $hits[0]['observaciones']);
        self::assertSame('111', $hits[0]['concepto_codigo']);
        self::assertSame('Iberdrola cocina', $hits[1]['observaciones']);
        self::assertCount(1, $buscar->ejecutar('iberdrola', 'P', 'bb'));
        self::assertSame([], $buscar->ejecutar('iberdrola', 'P', ''));
        self::assertSame([], $buscar->ejecutar('a', 'P', 'aa'));
    }

    public function testCuentaInvalida(): void
    {
        $buscar = new BuscarSugerenciasObservacion(
            new PdoAsientoRepository($this->pdo),
            new ResolverAmbitoActual(
                new PdoConfiguracionRepository($this->pdo),
                new PdoCentroRepository($this->pdo),
                new PdoEjercicioRepository($this->pdo),
            ),
        );
        $this->expectException(\InvalidArgumentException::class);
        $buscar->ejecutar('luz', 'X', 'aa');
    }
}
