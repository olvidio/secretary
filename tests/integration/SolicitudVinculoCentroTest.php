<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\entity\Identidad;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\application\AprobarSolicitudVinculoCentro;
use src\personas\application\ListarSolicitudesVinculoCentro;
use src\personas\application\SolicitarVinculoCentro;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\personas\infrastructure\persistence\PdoSolicitudVinculoCentroRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class SolicitudVinculoCentroTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_vinculo_centro';

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

    public function testSolicitudAprobadaVinculaPersonaExistente(): void
    {
        $centros = new PdoCentroRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $identidades = new PdoIdentidadRepository($this->pdo);
        $solicitudes = new PdoSolicitudVinculoCentroRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);

        $centro = $centros->listar()[0];
        self::assertNotNull($centro->id);
        $anio = 2026;

        $existente = $personas->guardar(new Persona(
            null,
            'Ana',
            'García',
            'ag',
            null,
            null,
            null,
            null,
            null,
            1,
            $centro->id,
            true,
            null,
            true,
        ));
        self::assertNotNull($existente->id);

        $identidad = $identidades->guardar(new Identidad(
            null,
            'ana@test.local',
            password_hash('secret', PASSWORD_DEFAULT),
            'Ana García',
            true,
            0,
            null,
            null,
            'ana',
        ));
        self::assertNotNull($identidad->id);
        self::assertFalse($identidades->tienePersonaEnAlgunCentro($identidad->id));

        $solicitar = new SolicitarVinculoCentro($solicitudes, $identidades, $centros);
        $sol = $solicitar->ejecutar($identidad->id, [
            'centro_id' => $centro->id,
            'anio' => $anio,
            'mensaje' => 'Soy Ana',
        ]);
        self::assertSame('pendiente', $sol['estado']);

        $listar = new ListarSolicitudesVinculoCentro($solicitudes, $identidades, $centros);
        self::assertCount(1, $listar->ejecutar($centro->id));

        $aprobar = new AprobarSolicitudVinculoCentro(
            $solicitudes,
            $personas,
            $identidades,
            $centros,
            new AsegurarCuentaCorrientePersona($cuentas),
            new AsegurarPlanPersonal($cuentas),
        );
        $resultado = $aprobar->ejecutar($centro->id, (int) $sol['id'], $identidad->id, [
            'persona_id' => $existente->id,
        ]);
        self::assertSame('ag', $resultado['iniciales']);
        self::assertTrue($identidades->tienePersonaEnCentro($identidad->id, $centro->id));
        self::assertTrue($identidades->tienePersonaEnAlgunCentro($identidad->id));
        self::assertSame($anio, $identidades->anioVinculoPersona($identidad->id, $existente->id));
        self::assertSame([], $listar->ejecutar($centro->id));
    }
}
