<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\entity\Identidad;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\personas\application\BorrarPersona;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class BorrarPersonaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_borrar_persona';

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

    public function testBorrarDesvinculaCuentaPersonalYLimpiaCorreo(): void
    {
        $centros = new PdoCentroRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $identidades = new PdoIdentidadRepository($this->pdo);

        $centro = $centros->listar()[0];
        self::assertNotNull($centro->id);

        $persona = $personas->guardar(new Persona(
            null,
            'Lluís',
            'Test',
            'lt',
            null,
            null,
            null,
            null,
            null,
            1,
            $centro->id,
            true,
            'lluis@test.local',
            true,
        ));
        self::assertNotNull($persona->id);

        $identidad = $identidades->guardar(new Identidad(
            null,
            'lluis@test.local',
            password_hash('secret', PASSWORD_DEFAULT),
            'Lluís Test',
            true,
            0,
            null,
            null,
            'lluis',
        ));
        self::assertNotNull($identidad->id);
        $identidades->vincularPersona($identidad->id, $persona->id, 2026);
        self::assertTrue($identidades->tienePersonaEnCentro($identidad->id, $centro->id));

        $borrar = new BorrarPersona($personas, $identidades, $this->pdo);
        $resultado = $borrar->ejecutar($persona->id, $centro->id);
        self::assertTrue($resultado['eliminada']);
        self::assertFalse($identidades->tienePersonaEnCentro($identidad->id, $centro->id));
        self::assertNull($personas->porId($persona->id));
    }
}
