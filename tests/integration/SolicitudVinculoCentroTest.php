<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\VincularEmailPersona;
use src\acceso\domain\entity\Identidad;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\ambito\domain\entity\Centro;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\plan\domain\services\CatalogoPlanesContables;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\application\AprobarSolicitudVinculoCentro;
use src\personas\application\EtiquetaCentroParaPersona;
use src\personas\application\ListarCandidatosVinculoCentro;
use src\personas\application\ListarCentrosDisponiblesPersona;
use src\personas\application\ListarSolicitudesVinculoCentro;
use src\personas\application\SolicitarVinculoCentro;
use src\acceso\application\RegistrarCentro;
use Tests\Soporte\ServiciosAcceso;
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

        $candidatos = new ListarCandidatosVinculoCentro($solicitudes, $personas, $identidades);
        $parecidos = $candidatos->ejecutar($centro->id, (int) $sol['id']);
        self::assertNotEmpty($parecidos);
        self::assertSame($existente->id, $parecidos[0]['id']);

        $aprobar = new AprobarSolicitudVinculoCentro(
            $solicitudes,
            $personas,
            $identidades,
            $centros,
            new AsegurarCuentaCorrientePersona($cuentas),
            new AsegurarPlanPersonal($cuentas),
            $this->pdo,
        );
        $resultado = $aprobar->ejecutar($centro->id, (int) $sol['id'], $identidad->id, [
            'persona_id' => $existente->id,
        ]);
        self::assertSame('ag', $resultado['iniciales']);
        self::assertTrue($identidades->tienePersonaEnCentro($identidad->id, $centro->id));
        self::assertTrue($identidades->tienePersonaEnAlgunCentro($identidad->id));
        self::assertSame($anio, $identidades->anioVinculoPersona($identidad->id, $existente->id));
        self::assertSame([], $listar->ejecutar($centro->id));
        $releida = $personas->porId($existente->id);
        self::assertSame('ana@test.local', $releida?->email);
    }

    public function testCentrosDisponiblesMuestranNombreDelCentroNoDelSecretario(): void
    {
        $centros = new PdoCentroRepository($this->pdo);
        $identidades = new PdoIdentidadRepository($this->pdo);
        $alta = (new RegistrarCentro(
            ServiciosAcceso::crearCentro($this->pdo),
            $identidades,
        ))->ejecutar(
            'casa-z',
            'Casa Zeta',
            'n',
            'sec-z',
            'sec-z@test.local',
            'secret1',
            'secret1',
            'Nombre Secretario',
            true,
        );
        self::assertSame('Casa Zeta', $alta['centro']->nombre);

        $personaId = $identidades->guardar(new Identidad(
            null,
            'yo@test.local',
            password_hash('secret', PASSWORD_DEFAULT),
            'Yo Personal',
            true,
            0,
            null,
            null,
            'yo',
        ));
        self::assertNotNull($personaId->id);

        $listar = new ListarCentrosDisponiblesPersona(
            $centros,
            $identidades,
            new EtiquetaCentroParaPersona($identidades),
        );
        $disponibles = $listar->ejecutar($personaId->id);
        $casaZ = null;
        foreach ($disponibles as $fila) {
            if (($fila['codigo'] ?? '') === 'casa-z') {
                $casaZ = $fila;
                break;
            }
        }
        self::assertNotNull($casaZ);
        self::assertSame('Casa Zeta', $casaZ['nombre_listado']);

        $this->pdo->prepare('UPDATE centros SET nombre = :n WHERE id = :id')->execute([
            ':n' => 'Nombre Secretario',
            ':id' => $alta['centro']->id,
        ]);
        $centroCorrupto = $centros->porId((int) $alta['centro']->id);
        self::assertNotNull($centroCorrupto);
        self::assertSame('Casa Z', (new EtiquetaCentroParaPersona($identidades))->ejecutar($centroCorrupto));
    }

    public function testSolicitudAprobadaAltaNuevaCopiaCorreo(): void
    {
        $centros = new PdoCentroRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $identidades = new PdoIdentidadRepository($this->pdo);
        $solicitudes = new PdoSolicitudVinculoCentroRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);

        $centro = $centros->listar()[0];
        self::assertNotNull($centro->id);

        $identidad = $identidades->guardar(new Identidad(
            null,
            'pepe@test.local',
            password_hash('secret', PASSWORD_DEFAULT),
            'Pepe López',
            true,
            0,
            null,
            null,
            'pepe',
        ));
        self::assertNotNull($identidad->id);

        $solicitar = new SolicitarVinculoCentro($solicitudes, $identidades, $centros);
        $sol = $solicitar->ejecutar($identidad->id, [
            'centro_id' => $centro->id,
            'anio' => 2026,
        ]);

        $aprobar = new AprobarSolicitudVinculoCentro(
            $solicitudes,
            $personas,
            $identidades,
            $centros,
            new AsegurarCuentaCorrientePersona($cuentas),
            new AsegurarPlanPersonal($cuentas),
            $this->pdo,
        );
        $resultado = $aprobar->ejecutar($centro->id, (int) $sol['id'], $identidad->id);
        self::assertSame('pepe', $resultado['iniciales']);

        $creada = $personas->porId((int) $resultado['id']);
        self::assertSame('pepe@test.local', $creada?->email);
    }

    public function testAprobarCopiaCorreoAunqueElLibroPersonalYaLoTenga(): void
    {
        $centros = new PdoCentroRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $identidades = new PdoIdentidadRepository($this->pdo);
        $solicitudes = new PdoSolicitudVinculoCentroRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);

        $centro = $centros->listar()[0];
        self::assertNotNull($centro->id);

        $libro = $centros->guardar(new Centro(
            null,
            'p-ana',
            'Ana',
            'p',
            'vivienda',
            CatalogoPlanesContables::H16N,
            true,
        ));
        self::assertNotNull($libro->id);

        $identidad = $identidades->guardar(new Identidad(
            null,
            'ana.libro@test.local',
            password_hash('secret', PASSWORD_DEFAULT),
            'Ana García',
            true,
            0,
            null,
            null,
            'analibro',
        ));
        self::assertNotNull($identidad->id);

        $delLibro = $personas->guardar(new Persona(
            null,
            'Ana',
            'García',
            'ana',
            null,
            null,
            null,
            null,
            null,
            0,
            $libro->id,
            true,
            'ana.libro@test.local',
            true,
        ));
        self::assertNotNull($delLibro->id);
        $identidades->vincularPersona($identidad->id, $delLibro->id, 2026);

        $existente = $personas->guardar(new Persona(
            null,
            'Ana',
            'García',
            'ag2',
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

        $solicitar = new SolicitarVinculoCentro($solicitudes, $identidades, $centros);
        $sol = $solicitar->ejecutar($identidad->id, [
            'centro_id' => $centro->id,
            'anio' => 2026,
        ]);

        $aprobar = new AprobarSolicitudVinculoCentro(
            $solicitudes,
            $personas,
            $identidades,
            $centros,
            new AsegurarCuentaCorrientePersona($cuentas),
            new AsegurarPlanPersonal($cuentas),
            $this->pdo,
        );
        $resultado = $aprobar->ejecutar($centro->id, (int) $sol['id'], $identidad->id, [
            'persona_id' => $existente->id,
        ]);

        self::assertSame('ag2', $resultado['iniciales']);
        $releida = $personas->porId($existente->id);
        self::assertSame('ana.libro@test.local', $releida?->email);
        self::assertSame('ana.libro@test.local', $personas->porId($delLibro->id)?->email);
        self::assertSame([], (new ListarSolicitudesVinculoCentro($solicitudes, $identidades, $centros))->ejecutar($centro->id));

        $guardada = $personas->porId($existente->id);
        self::assertNotNull($guardada);
        self::assertNull((new VincularEmailPersona($identidades, $personas, $centros))->ejecutar(
            $guardada,
            'ana.libro@test.local',
        ));
    }

    public function testReintentoCompletaVinculoDejadoAMedias(): void
    {
        $centros = new PdoCentroRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $identidades = new PdoIdentidadRepository($this->pdo);
        $solicitudes = new PdoSolicitudVinculoCentroRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);

        $centro = $centros->listar()[0];
        self::assertNotNull($centro->id);

        $existente = $personas->guardar(new Persona(
            null,
            'Luis',
            'Marín',
            'lm',
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
            'luis@test.local',
            password_hash('secret', PASSWORD_DEFAULT),
            'Luis Marín',
            true,
            0,
            null,
            null,
            'luis',
        ));
        self::assertNotNull($identidad->id);

        $solicitar = new SolicitarVinculoCentro($solicitudes, $identidades, $centros);
        $sol = $solicitar->ejecutar($identidad->id, [
            'centro_id' => $centro->id,
            'anio' => 2026,
        ]);
        $identidades->vincularPersona($identidad->id, $existente->id, 2026);
        self::assertNull($personas->porId($existente->id)?->email);

        $aprobar = new AprobarSolicitudVinculoCentro(
            $solicitudes,
            $personas,
            $identidades,
            $centros,
            new AsegurarCuentaCorrientePersona($cuentas),
            new AsegurarPlanPersonal($cuentas),
            $this->pdo,
        );
        $resultado = $aprobar->ejecutar($centro->id, (int) $sol['id'], $identidad->id, [
            'persona_id' => $existente->id,
        ]);

        self::assertSame('lm', $resultado['iniciales']);
        self::assertSame('luis@test.local', $personas->porId($existente->id)?->email);
        self::assertSame([], (new ListarSolicitudesVinculoCentro($solicitudes, $identidades, $centros))->ejecutar($centro->id));
    }
}
