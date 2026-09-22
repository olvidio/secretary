<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\AsegurarIdentidadCentro;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\administracion\application\EliminarCentro;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\application\AsegurarCuentaDisponiblePersona;
use src\ambito\application\CrearCentro;
use src\ambito\application\CrearEjercicio;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\application\SincronizarConfiguracionConEjercicio;
use src\ambito\application\VaciarDatosCentro;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\ambito\infrastructure\persistence\PdoPobladorCentro;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\cierre\application\GenerarApertura;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\application\GuardarPersona;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class EliminarCentroTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_eliminar_centro';

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

    public function testBorrarCentroConPersonasYCuentasPersonales(): void
    {
        $centros = new PdoCentroRepository($this->pdo);
        $alta = $this->crearCentroService()->ejecutar([
            'codigo' => 'BORRAR-ME',
            'nombre' => 'Centro efímero',
            'tipo_cierre' => 'vivienda',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'usuario' => 'sec-borrar',
            'email' => 'sec-borrar@test.local',
            'password' => 'secret',
            'verificar_email' => false,
        ]);
        self::assertNotNull($alta['centro']->id);
        $centroId = $alta['centro']->id;

        $this->guardarPersonaService($centroId)->ejecutar([
            'nombre' => 'Anna',
            'apellidos' => 'Test',
            'iniciales' => 'at',
        ]);

        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM cuentas WHERE centro_id = :c AND persona_id IS NOT NULL'
        );
        $st->execute([':c' => $centroId]);
        self::assertGreaterThan(0, (int) $st->fetchColumn());

        $eliminar = new EliminarCentro(
            $this->pdo,
            $centros,
            new PdoEjercicioRepository($this->pdo),
            new VaciarDatosCentro(
                $this->pdo,
                new PdoEjercicioRepository($this->pdo),
                new PdoAsientoRepository($this->pdo),
            ),
        );
        $eliminar->ejecutar($centroId, true);

        self::assertNull($centros->porId($centroId));
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM personas WHERE centro_id = :c');
        $st->execute([':c' => $centroId]);
        self::assertSame(0, (int) $st->fetchColumn());
    }

    private function crearCentroService(): CrearCentro
    {
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $config = new PdoConfiguracionRepository($this->pdo);

        return new CrearCentro(
            $this->pdo,
            new PdoCentroRepository($this->pdo),
            new CrearEjercicio(
                $ejercicios,
                new GenerarApertura($ejercicios, $asientos, $cuentas),
                new SincronizarConfiguracionConEjercicio($config),
            ),
            new PdoPobladorCentro($this->pdo),
            new AsegurarIdentidadCentro(new PdoIdentidadRepository($this->pdo)),
            new \src\plan\infrastructure\persistence\PdoPartidaLaboresRepository(
                $this->pdo,
                new \src\plan\infrastructure\persistence\PdoPlanConceptoRepository($this->pdo),
            ),
            new \src\plan\infrastructure\persistence\PdoPlanContableRepository($this->pdo),
        );
    }

    private function guardarPersonaService(int $centroId): GuardarPersona
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new \src\personas\infrastructure\persistence\PdoPersonaRepository($this->pdo);
        $identidades = new PdoIdentidadRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);

        return new GuardarPersona(
            $personas,
            new ResolverAmbitoActual($config, $centros, $ejercicios, $centroId),
            new \src\acceso\application\VincularEmailPersona($identidades, $personas, $centros),
            new AsegurarCuentaCorrientePersona($cuentas),
            new AsegurarCuentaDisponiblePersona($cuentas),
            new AsegurarPlanPersonal($cuentas),
            $config,
        );
    }
}
