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
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\personas\application\GuardarNumSCentroSg;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class NumSCentroSgTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_num_s_sg';

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

    public function testGuardarNumSEnCentro(): void
    {
        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $centro = $centros->guardar(new Centro(
            null,
            'SG-NS',
            'Test num s',
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

        $ambito = new ResolverAmbitoActual(
            new \src\configuracion\infrastructure\persistence\PdoConfiguracionRepository($this->pdo),
            $centros,
            $ejercicios,
            $centro->id,
        );
        $guardar = new GuardarNumSCentroSg($centros, $ambito);
        self::assertSame(0, $centros->numS($centro->id));
        self::assertSame(18, $guardar->ejecutar(18));
        self::assertSame(18, $centros->numS($centro->id));
    }
}
