<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\AutorizarPeticion;
use src\acceso\application\IniciarSesion;
use src\acceso\domain\entity\Identidad;
use src\acceso\infrastructure\persistence\AccesoSeeder;
use src\acceso\infrastructure\persistence\PdoAccesoRutaRepository;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\informes\application\CalcularSaldos;
use src\personal\application\AsegurarPlanPersonal;
use src\personal\application\CrearSubcuentaPersonal;
use src\personal\application\ListarMovimientosPersonales;
use src\personal\application\RegistrarMovimientoPersonal;
use src\personal\application\ResolverPersonaActual;
use src\personal\infrastructure\persistence\Nivel1Seeder;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/** Fase 7 (D5): libro personal X aislado del centro. */
final class Nivel1Test extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_nivel1';

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

    public function testAislamientoEntrePersonasYRespectoAlCentro(): void
    {
        $deps = $this->deps();
        $fecha = $deps['anio'] . '-03-15';
        $saldosAntes = $deps['saldos']->ejecutar($fecha);
        $cajaAntes = $saldosAntes['caja'];

        $catA = $deps['cuentas']->buscar($deps['centroId'], $deps['personaA'], 'X', '22');
        self::assertNotNull($catA?->id);

        $deps['registrarA']->ejecutar([
            'sentido' => 'gasto',
            'fecha' => $fecha,
            'cantidad' => '12.50',
            'cuenta_id' => $catA->id,
            'tesoreria' => 'CAJA',
            'nota' => 'Pan',
        ]);

        $movsA = $deps['listarA']->ejecutar($deps['anio'] . '-03-01', $deps['anio'] . '-03-31');
        self::assertCount(1, $movsA);
        self::assertSame('gasto', $movsA[0]['sentido']);
        self::assertSame('12.50', $movsA[0]['cantidad']);

        $movsB = $deps['listarB']->ejecutar($deps['anio'] . '-03-01', $deps['anio'] . '-03-31');
        self::assertSame([], $movsB);

        $saldosDespues = $deps['saldos']->ejecutar($fecha);
        self::assertSame($cajaAntes, $saldosDespues['caja'], 'El gasto del libro X no puede mover la caja del centro');

        $x = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM asientos WHERE libro = 'X' AND anulado_at IS NULL"
        )->fetchColumn();
        $pg = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM asientos WHERE libro IN ('P', 'G') AND anulado_at IS NULL"
        )->fetchColumn();
        self::assertSame(1, $x);
        self::assertSame(0, $pg);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('El código maestro no existe');
        $deps['subcuentaA']->ejecutar([
            'codigo_maestro' => 'inventado',
            'codigo' => 'gas',
            'nombre' => 'Gas',
        ]);
    }

    public function testSubcuentaConMaestroValidoYRechazoDel9(): void
    {
        $deps = $this->deps();
        $cuenta = $deps['subcuentaA']->ejecutar([
            'codigo_maestro' => '21',
            'codigo' => 'gas',
            'nombre' => 'Gas de casa',
        ]);
        self::assertSame('21.gas', $cuenta->codigo);
        self::assertSame('21', $cuenta->codigoMaestro);

        $this->expectException(\InvalidArgumentException::class);
        $deps['subcuentaA']->ejecutar([
            'codigo_maestro' => '9',
            'codigo' => 'cc',
            'nombre' => 'No',
        ]);
    }

    public function testIdentidadYoYAutorizacionCruzada(): void
    {
        $deps = $this->deps();
        $login = (new IniciarSesion($deps['identidades']))->ejecutar('yo', 'cambiar');
        self::assertSame('autenticado', $login->estado);
        self::assertSame('persona', $login->nivel);
        self::assertSame($deps['personaA'], $login->personaId);

        $auth = new AutorizarPeticion(new PdoAccesoRutaRepository($this->pdo), $deps['identidades']);
        $centroApi = $auth->ejecutar(
            'src\\apuntes\\infrastructure\\http\\ApunteController',
            'list',
            'GET',
            true,
            (int) $login->identidadId,
            null,
            $deps['centroId'],
            'persona',
            true,
            $deps['personaA'],
        );
        self::assertFalse($centroApi->permitido);
        self::assertSame(403, $centroApi->status);

        $yoApi = $auth->ejecutar(
            'src\\personal\\infrastructure\\http\\PersonalController',
            'resumen',
            'GET',
            true,
            (int) $login->identidadId,
            null,
            null,
            'persona',
            true,
            $deps['personaA'],
        );
        self::assertTrue($yoApi->permitido);

        $centroEnYo = $auth->ejecutar(
            'src\\personal\\infrastructure\\http\\PersonalController',
            'resumen',
            'GET',
            true,
            1,
            null,
            $deps['centroId'],
            'centro',
            true,
        );
        self::assertFalse($centroEnYo->permitido);
    }

    /**
     * @return array<string, mixed>
     */
    private function deps(): array
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $cuentas = new PdoCuentaRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $asientos = new PdoAsientoRepository($this->pdo);
        $identidades = new PdoIdentidadRepository($this->pdo);
        $asegurar = new AsegurarPlanPersonal($cuentas);
        $ambitoCentro = new ResolverAmbitoActual($config, $centros, $ejercicios);
        $ctx = $ambitoCentro->ejecutar();
        $centroId = $ctx->centroId;
        $anio = (int) $config->get()->anio;

        $personaA = $personas->guardar(new Persona(
            null,
            'Ana',
            'Alfa',
            'aa',
            null,
            null,
            null,
            null,
            null,
            1,
            $centroId,
        ));
        $personaB = $personas->guardar(new Persona(
            null,
            'Berta',
            'Beta',
            'bb',
            null,
            null,
            null,
            null,
            null,
            2,
            $centroId,
        ));
        self::assertNotNull($personaA->id);
        self::assertNotNull($personaB->id);

        AccesoSeeder::sembrar($this->pdo);
        Nivel1Seeder::sembrar($this->pdo);

        $idB = $identidades->guardar(new Identidad(
            null,
            'berta@example.test',
            password_hash('clave', PASSWORD_DEFAULT),
            'Berta',
            true,
            0,
            null,
            null,
            'berta',
        ));
        self::assertNotNull($idB->id);
        $identidades->vincularPersona($idB->id, $personaB->id);

        $yo = $identidades->porEmailOAlias('yo');
        self::assertNotNull($yo?->id);

        $resolverA = new ResolverPersonaActual($identidades, $personas, $ejercicios, $asegurar, $yo->id, $personaA->id);
        $resolverB = new ResolverPersonaActual($identidades, $personas, $ejercicios, $asegurar, $idB->id, $personaB->id);

        return [
            'centroId' => $centroId,
            'anio' => $anio,
            'personaA' => $personaA->id,
            'personaB' => $personaB->id,
            'cuentas' => $cuentas,
            'identidades' => $identidades,
            'registrarA' => new RegistrarMovimientoPersonal($resolverA, $cuentas, $ejercicios, $asientos),
            'listarA' => new ListarMovimientosPersonales($resolverA, $asientos, $cuentas),
            'listarB' => new ListarMovimientosPersonales($resolverB, $asientos, $cuentas),
            'subcuentaA' => new CrearSubcuentaPersonal($resolverA, $cuentas),
            'saldos' => new CalcularSaldos($asientos, $config, $personas, $ambitoCentro),
        ];
    }
}
