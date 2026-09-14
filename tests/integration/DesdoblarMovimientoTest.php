<?php

declare(strict_types=1);

namespace Tests\integration;

use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\acceso\infrastructure\persistence\AccesoSeeder;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\personal\application\AsegurarPlanPersonal;
use src\personal\application\DesdoblarMovimientoPersonal;
use src\personal\application\ListarMovimientosPersonales;
use src\personal\application\RegistrarMovimientoPersonal;
use src\personal\application\ResolverPersonaActual;
use src\personal\infrastructure\persistence\Nivel1Seeder;
use src\personal\infrastructure\persistence\PdoBancoImportRepository;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

final class DesdoblarMovimientoTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_desdoblar';

    public function testDesdoblarGastoEnDosCategorias(): void
    {
        $this->saltarSiNoHayPgsql();
        try {
            $pdo = $this->prepararBaseDeTestVacia(self::DB_NAME);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base: ' . $e->getMessage());
        }
        (new SchemaInstaller($pdo))->install();

        $personas = new PdoPersonaRepository($pdo);
        $cuentas = new PdoCuentaRepository($pdo);
        $ejercicios = new PdoEjercicioRepository($pdo);
        $asientos = new PdoAsientoRepository($pdo);
        $plan = new AsegurarPlanPersonal($cuentas);
        $config = new PdoConfiguracionRepository($pdo);
        $anio = (int) $config->get()->anio;
        $centroId = (int) $pdo->query('SELECT id FROM centros ORDER BY id LIMIT 1')->fetchColumn();
        $persona = $personas->guardar(new Persona(
            null,
            'Nerea',
            'Ene',
            'ne',
            null,
            null,
            null,
            null,
            null,
            1,
            $centroId,
        ));
        self::assertNotNull($persona->id);
        AccesoSeeder::sembrar($pdo);
        Nivel1Seeder::sembrar($pdo);
        $identidades = new PdoIdentidadRepository($pdo);
        $yo = $identidades->porEmailOAlias('yo');
        self::assertNotNull($yo?->id);
        $identidades->vincularPersona($yo->id, $persona->id);

        $resolver = new ResolverPersonaActual(
            $identidades,
            $personas,
            $ejercicios,
            $plan,
            $yo->id,
            $persona->id,
        );
        $fecha = $anio . '-08-25';
        $banco = $cuentas->tesoreriaDePersona($centroId, $persona->id, 'X', 'BANCO');
        $pendiente = $cuentas->buscar($centroId, $persona->id, 'X', AsegurarPlanPersonal::CODIGO_PENDIENTE_GASTO);
        $cat22 = $cuentas->buscar($centroId, $persona->id, 'X', '22');
        self::assertNotNull($banco?->id);
        self::assertNotNull($pendiente?->id);
        self::assertNotNull($cat22?->id);

        $registrar = new RegistrarMovimientoPersonal($resolver, $cuentas, $ejercicios, $asientos, $personas);
        $guardado = $registrar->ejecutar([
            'sentido' => 'gasto',
            'fecha' => $fecha,
            'cantidad' => '700',
            'cuenta_id' => $pendiente->id,
            'tesoreria' => 'BANCO',
            'nota' => 'REINT.CAIXER',
        ]);
        self::assertCount(1, $guardado);
        self::assertNotNull($guardado[0]->id);

        $desdoblar = new DesdoblarMovimientoPersonal(
            $resolver,
            $cuentas,
            $asientos,
            new PdoBancoImportRepository($pdo),
            $pdo,
        );
        $partidos = $desdoblar->ejecutar($guardado[0]->id, [
            'partes' => [
                ['cantidad' => '300', 'cuenta_id' => $cat22->id, 'nota' => 'Vivienda'],
                ['cantidad' => '400', 'cuenta_id' => $cat22->id, 'nota' => 'Club'],
            ],
        ]);
        self::assertCount(2, $partidos);

        $movs = (new ListarMovimientosPersonales($resolver, $asientos, $cuentas))
            ->ejecutar($anio . '-08-01', $anio . '-08-31');
        self::assertCount(2, $movs);
        $importes = array_map(static fn (array $m): float => (float) $m['cantidad'], $movs);
        sort($importes);
        self::assertSame([300.0, 400.0], $importes);
        self::assertSame('BANCO', $movs[0]['tesoreria']);
        self::assertSame('Vivienda', $movs[0]['nota']);
        self::assertSame('Club', $movs[1]['nota']);
    }
}
