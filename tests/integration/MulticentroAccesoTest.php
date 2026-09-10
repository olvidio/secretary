<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\AsegurarIdentidadCentro;
use src\acceso\application\IniciarSesion;
use src\acceso\application\VincularEmailPersona;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\application\CrearCentro;
use src\ambito\application\CrearEjercicio;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\application\SincronizarConfiguracionConEjercicio;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\ambito\infrastructure\persistence\PdoPobladorCentro;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\ambito\application\VaciarDatosCentro;
use src\apuntes\infrastructure\persistence\PdoApunteRepository;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\cierre\application\GenerarApertura;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\application\GuardarPersona;
use src\personas\application\ListarPersonas;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/** Fase 9 (parcial): un secretario y un correo personal viven en un solo centro. */
final class MulticentroAccesoTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_multicentro';

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

    public function testSclYScl2VenCentrosDistintosYElEmailDeNombresAbreElLibroPersonal(): void
    {
        $identidades = new PdoIdentidadRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $iniciar = new IniciarSesion($identidades);

        $loginScl = $iniciar->ejecutar('scl', 'cambiar');
        self::assertSame('centro', $loginScl->nivel);
        self::assertCount(1, $loginScl->centros);
        $centroA = $loginScl->centros[0]['centro_id'];

        $crear = $this->crearCentroService();
        $alta = $crear->ejecutar([
            'codigo' => 'CASA-B',
            'nombre' => 'Casa B',
            'tipo_cierre' => 'vivienda',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'usuario' => 'scl2',
            'email' => 'scl2@secretario.local',
            'password' => 'cambiar',
        ]);
        $centroB = (int) $alta['centro']->id;
        self::assertNotSame($centroA, $centroB);

        $loginScl2 = $iniciar->ejecutar('scl2', 'cambiar');
        self::assertSame('pendiente_activar', $loginScl2->estado);
        self::assertSame('centro', $loginScl2->nivel);
        self::assertCount(1, $loginScl2->centros);
        self::assertSame($centroB, $loginScl2->centros[0]['centro_id']);

        $loginSclOtraVez = $iniciar->ejecutar('scl', 'cambiar');
        self::assertCount(1, $loginSclOtraVez->centros);
        self::assertSame($centroA, $loginSclOtraVez->centros[0]['centro_id']);

        $personas->guardar(new Persona(null, 'Ana', 'A', 'aa', null, null, null, null, null, 1, $centroA));
        $guardarB = $this->guardarPersona($centroB);
        $resultado = $guardarB->ejecutar([
            'nombre' => 'Bea',
            'apellidos' => 'B',
            'iniciales' => 'aa',
            'email' => 'bea@example.test',
        ]);
        $bea = $resultado['persona'];
        self::assertNotNull($bea->id);
        self::assertSame($centroB, $bea->centroId);
        self::assertSame('bea@example.test', $bea->email);
        self::assertNotNull($resultado['password_inicial']);
        $nombresA = (new ListarPersonas($personas))->ejecutarDeCentro($centroA);
        self::assertCount(1, $nombresA);
        self::assertSame('aa', $nombresA[0]['iniciales']);
        self::assertSame('', $nombresA[0]['email']);
        $nombresB = (new ListarPersonas($personas))->ejecutarDeCentro($centroB);
        self::assertCount(1, $nombresB);
        self::assertSame('bea@example.test', $nombresB[0]['email']);
        self::assertSame('aa', $nombresB[0]['iniciales']);

        $cuentas = new PdoCuentaRepository($this->pdo);
        self::assertNotNull($cuentas->personalDe($centroB, (int) $bea->id));
        self::assertNotNull($cuentas->buscar($centroB, (int) $bea->id, 'X', '22'));

        $loginBea = $iniciar->ejecutar('bea@example.test', (string) $resultado['password_inicial']);
        self::assertSame('autenticado', $loginBea->estado);
        self::assertSame('persona', $loginBea->nivel);
        self::assertSame($bea->id, $loginBea->personaId);
        self::assertSame([], $loginBea->centros);
    }

    public function testImportarYVaciarExcelSoloAfectaAlCentroDestino(): void
    {
        $excelPath = dirname(__DIR__, 2) . '/moviments2026.xlsm';
        if (!is_readable($excelPath)) {
            self::markTestSkipped('Falta moviments2026.xlsm; no se puede probar la importación por centro.');
        }

        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $config = new PdoConfiguracionRepository($this->pdo);
        $centroA = $centros->porCodigo(trim($config->get()->centro)) ?? $centros->listar()[0];
        self::assertNotNull($centroA?->id);
        $centroAId = (int) $centroA->id;
        $configAntes = $config->get()->centro;
        $asientosAAntes = $this->contarAsientosDeCentro($centroAId);

        $alta = $this->crearCentroService()->ejecutar([
            'codigo' => 'CASA-B',
            'nombre' => 'Casa B',
            'tipo_cierre' => 'vivienda',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'usuario' => 'scl2',
            'email' => 'scl2@secretario.local',
            'password' => 'cambiar',
        ]);
        $centroBId = (int) $alta['centro']->id;
        $ejercicioBId = (int) $alta['ejercicio']->id;

        $importar = $this->importador();
        $res = $importar->ejecutar($excelPath, true, false, 'CASA-B');
        self::assertGreaterThan(0, (int) $res['personas']);
        self::assertGreaterThan(0, (int) $res['asientos']);
        self::assertSame($configAntes, $config->get()->centro);
        self::assertSame($asientosAAntes, $this->contarAsientosDeCentro($centroAId));
        self::assertGreaterThan(0, $this->contarAsientosDeCentro($centroBId));

        $vaciar = new VaciarDatosCentro($this->pdo, $ejercicios, new PdoAsientoRepository($this->pdo));
        $vaciado = $vaciar->ejecutar($centroBId, true);
        self::assertGreaterThan(0, $vaciado['asientos']);
        self::assertSame(0, $this->contarAsientosDeCentro($centroBId));
        self::assertSame($asientosAAntes, $this->contarAsientosDeCentro($centroAId));
        $personas = new PdoPersonaRepository($this->pdo);
        self::assertGreaterThan(0, count($personas->listarDeCentro($centroBId)));

        $otraVez = $importar->ejecutar($excelPath, true, false, 'CASA-B');
        self::assertGreaterThan(0, (int) $otraVez['asientos']);
        self::assertSame($asientosAAntes, $this->contarAsientosDeCentro($centroAId));
        self::assertGreaterThan(0, $this->contarAsientosDeCentro($centroBId));
        self::assertNotNull($ejercicios->porId($ejercicioBId));
    }

    public function testNoSePuedeReutilizarSclConOtroCorreoEnUnCentroNuevo(): void
    {
        $crear = $this->crearCentroService();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ya existe con otro correo');
        $crear->ejecutar([
            'codigo' => 'CASA-C',
            'nombre' => 'Casa C',
            'tipo_cierre' => 'vivienda',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'usuario' => 'scl',
            'email' => 'otro@secretario.local',
            'password' => 'cambiar',
        ]);
    }

    private function crearCentroService(): CrearCentro
    {
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $config = new PdoConfiguracionRepository($this->pdo);
        $crearEjercicio = new CrearEjercicio(
            $ejercicios,
            new GenerarApertura($ejercicios, $asientos, $cuentas),
            new SincronizarConfiguracionConEjercicio($config),
        );

        return new CrearCentro(
            $this->pdo,
            new PdoCentroRepository($this->pdo),
            $crearEjercicio,
            new PdoPobladorCentro($this->pdo),
            new AsegurarIdentidadCentro(new PdoIdentidadRepository($this->pdo)),
            new \src\plan\infrastructure\persistence\PdoPartidaLaboresRepository($this->pdo),
        );
    }

    private function guardarPersona(int $centroId): GuardarPersona
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $identidades = new PdoIdentidadRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, $centros, $ejercicios, $centroId);

        return new GuardarPersona(
            $personas,
            $ambito,
            new VincularEmailPersona($identidades, $personas),
            new AsegurarCuentaCorrientePersona($cuentas),
            new AsegurarPlanPersonal($cuentas),
            $config,
        );
    }

    private function importador(): ImportarExcelSecretario
    {
        return new ImportarExcelSecretario(
            $this->pdo,
            new PdoConfiguracionRepository($this->pdo),
            new PdoPersonaRepository($this->pdo),
            new PdoConceptoRepository($this->pdo),
            new PdoApunteRepository($this->pdo),
            new PdoPresupuestoRepository($this->pdo),
        );
    }

    private function contarAsientosDeCentro(int $centroId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM asientos a
             INNER JOIN ejercicios e ON e.id = a.ejercicio_id
             WHERE e.centro_id = :c AND a.anulado_at IS NULL'
        );
        $st->execute([':c' => $centroId]);

        return (int) $st->fetchColumn();
    }
}
