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
use src\apuntes\application\CrearApunte;
use src\apuntes\application\CrearApuntesDeEntrada;
use src\apuntes\domain\services\ContrapartidasGastoGeneral;
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

final class EntradaCentroSgTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_entrada_sg';

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

    public function testUnApunteConOrigenASeAnotaContraLaCaja(): void
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $centro = $centros->guardar(new Centro(
            null,
            'SG-TEST',
            'Centro sg de prueba',
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
        self::assertNull($cuentas->deudoresVivienda($centro->id));
        self::assertNotNull($cuentas->tesoreria($centro->id, 'G', 'CAJA'));

        $personas->guardar(new Persona(null, 'Ana', 'A', 'aa', null, null, null, null, null, 1, $centro->id));
        $ambito = new ResolverAmbitoActual($config, $centros, $ejercicios, $centro->id);
        $conceptos = ConceptosCentro::resolver($this->pdo);
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
        $crear = new CrearApuntesDeEntrada(
            $crearApunte,
            $conceptos,
            $ambito,
            $personas,
            new ContrapartidasGastoGeneral(),
            $centros,
        );

        $filas = $crear->ejecutar([
            'fecha' => '2026-03-15',
            'cuenta' => 'G',
            'origen' => 'A',
            'concepto_codigo' => '21',
            'observaciones' => 'luz',
            'cantidad' => '12.50',
        ]);
        self::assertCount(1, $filas);
        self::assertSame('G', $filas[0]->cuenta);
        self::assertSame('21', $filas[0]->conceptoCodigo);
        self::assertSame('C', $filas[0]->origen);

        $conNombre = $crear->ejecutar([
            'contrapartidas' => true,
            'fecha' => '2026-03-16',
            'cuenta' => 'G',
            'origen' => 'A',
            'iniciales' => 'aa',
            'concepto_codigo' => '21',
            'observaciones' => 'agua',
            'cantidad' => '8.00',
        ]);
        self::assertCount(1, $conNombre);
        self::assertSame('G/21', $conNombre[0]->cuenta . '/' . $conNombre[0]->conceptoCodigo);
        self::assertSame('C', $conNombre[0]->origen);
        self::assertSame('aa', $conNombre[0]->iniciales);
    }
}
