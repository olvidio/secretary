<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\application\GuardarPlantillaApunte;
use src\apuntes\application\ListarPlantillasApunte;
use src\apuntes\infrastructure\persistence\PdoPlantillaApunteRepository;
use src\apuntes\infrastructure\persistence\PlantillaApunteSeeder;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class PlantillaApunteTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_plantillas';

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

    public function testSeederCreaClubConCuatroMovimientos(): void
    {
        PlantillaApunteSeeder::sembrar($this->pdo);

        $ambito = new ResolverAmbitoActual(
            new PdoConfiguracionRepository($this->pdo),
            new PdoCentroRepository($this->pdo),
            new PdoEjercicioRepository($this->pdo),
        );
        $hits = (new ListarPlantillasApunte(new PdoPlantillaApunteRepository($this->pdo), $ambito))
            ->ejecutar('P');

        self::assertCount(1, $hits);
        self::assertSame('Club', $hits[0]['nombre']);
        self::assertCount(4, $hits[0]['lineas']);
        self::assertSame('P', $hits[0]['lineas'][0]['cuenta']);
        self::assertSame('21', $hits[0]['lineas'][0]['concepto_codigo']);
        self::assertSame('111', $hits[0]['lineas'][1]['concepto_codigo']);
        self::assertSame('G', $hits[0]['lineas'][2]['cuenta']);
        self::assertSame('11', $hits[0]['lineas'][2]['concepto_codigo']);
        self::assertSame('211', $hits[0]['lineas'][3]['concepto_codigo']);
        self::assertSame('ingres al club', $hits[0]['lineas'][3]['observaciones']);
    }

    public function testGuardarPlantillaMultilibro(): void
    {
        $ambito = new ResolverAmbitoActual(
            new PdoConfiguracionRepository($this->pdo),
            new PdoCentroRepository($this->pdo),
            new PdoEjercicioRepository($this->pdo),
        );

        $guardada = (new GuardarPlantillaApunte(
            new PdoPlantillaApunteRepository($this->pdo),
            new PdoConceptoRepository($this->pdo),
            $ambito,
        ))->ejecutar([
            'cuenta' => 'P',
            'nombre' => 'Club test',
            'lineas' => [
                ['cuenta' => 'P', 'origen' => 'A', 'concepto_codigo' => '21', 'observaciones' => 'per el club'],
                ['cuenta' => 'G', 'origen' => 'A', 'concepto_codigo' => '211', 'observaciones' => 'ingres al club'],
            ],
        ]);

        self::assertCount(2, $guardada['lineas']);
        self::assertSame('G', $guardada['lineas'][1]['cuenta']);
    }
}
