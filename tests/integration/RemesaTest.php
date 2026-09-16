<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\acceso\infrastructure\persistence\AccesoSeeder;
use src\acceso\infrastructure\persistence\PdoIdentidadRepository;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\apuntes\application\BorrarApunte;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\ListarApuntes;
use src\apuntes\application\CrearApuntesDeEntrada;
use src\apuntes\domain\services\ContrapartidasGastoGeneral;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\cierre\application\GenerarApertura;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\informes\application\CalcularSaldos;
use src\personal\application\AsegurarPlanPersonal;
use src\personal\application\CrearSubcuentaPersonal;
use src\personal\application\RegistrarMovimientoPersonal;
use src\personal\application\ResolverPeriodoPersonal;
use src\personal\application\ResolverPersonaActual;
use src\personal\infrastructure\persistence\Nivel1Seeder;
use src\personal\infrastructure\persistence\PdoPersonalCierreRepository;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\remesas\application\AceptarRemesa;
use src\remesas\application\EnviarRemesa;
use src\remesas\application\ObtenerDetalleRemesa;
use src\remesas\application\PrevisualizarRemesa;
use src\remesas\application\RechazarRemesa;
use src\remesas\application\RegistrarGastosGeneralesDeRemesa;
use src\remesas\application\RegistrarPlantillasDeRemesa;
use src\remesas\application\ResolverMesRemesa;
use src\remesas\application\ResolverSolicitudDetalle;
use src\remesas\application\SolicitarDetalleRemesa;
use src\remesas\infrastructure\persistence\PdoRemesaRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/** Fase 8 (D6): remesa versionada nivel 1 → nivel 2, sin duplicar asientos. */
final class RemesaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_remesas';

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

    public function testEnviarRechazarReenviarYAceptarDejaUnSoloAsiento(): void
    {
        $d = $this->deps();
        $anio = $d['anio'];
        $fecha = sprintf('%04d-01-10', $anio);
        $cajaAntes = $d['saldos']->ejecutar($fecha)['caja'];

        $d['subcuenta']->ejecutar([
            'codigo_maestro' => '22',
            'codigo' => 'gas',
            'nombre' => 'Gas de casa',
        ]);
        $gas = $d['cuentas']->buscar($d['centroId'], $d['personaId'], 'X', '22.gas');
        $trabajo = $d['cuentas']->buscar($d['centroId'], $d['personaId'], 'X', '111');
        self::assertNotNull($gas?->id);
        self::assertNotNull($trabajo?->id);

        $d['registrar']->ejecutar([
            'sentido' => 'gasto',
            'fecha' => $fecha,
            'cantidad' => '12.50',
            'cuenta_id' => $gas->id,
            'tesoreria' => 'CAJA',
            'nota' => 'Gas',
        ]);
        $d['registrar']->ejecutar([
            'sentido' => 'ingreso',
            'fecha' => $fecha,
            'cantidad' => '50.00',
            'cuenta_id' => $trabajo->id,
            'tesoreria' => 'CAJA',
            'nota' => 'Nómina',
        ]);
        $d['registrar']->ejecutar([
            'sentido' => 'traspaso',
            'fecha' => $fecha,
            'cantidad' => '5.00',
            'tesoreria_origen' => 'CAJA',
            'tesoreria_destino' => 'BANCO',
        ]);

        $prev = $d['preview']->ejecutar($anio, 1);
        $codigos = array_column($prev['lineas'], 'codigo_maestro');
        self::assertContains('22', $codigos);
        self::assertContains('111', $codigos);
        self::assertNotContains('CAJA', $codigos);
        $porCodigo = [];
        foreach ($prev['lineas'] as $l) {
            $porCodigo[$l['codigo_maestro']] = $l['importe_cents'];
        }
        self::assertSame(1250, $porCodigo['22']);
        self::assertSame(5000, $porCodigo['111']);

        $enviada = $d['enviar']->ejecutar(['anio' => $anio, 'mes' => 1]);
        self::assertSame('enviada', $enviada->estado);
        self::assertSame(1, $enviada->version);
        self::assertNotNull($enviada->id);

        $d['rechazar']->ejecutar((int) $enviada->id);
        self::assertSame(0, $this->contarAsientosRemesa());
        self::assertSame(0, $this->realizado22($d));

        $v2 = $d['enviar']->ejecutar(['anio' => $anio, 'mes' => 1]);
        self::assertSame(2, $v2->version);
        $d['aceptar']->ejecutar((int) $v2->id);
        self::assertSame(1, $this->contarAsientosRemesa());
        self::assertSame(1250, $this->realizado22($d));
        $apuntesRemesa = array_values(array_filter(
            $d['listar']->ejecutar(['iniciales' => 'aa']),
            static fn (array $a): bool => str_starts_with((string) ($a['observaciones'] ?? ''), 'Remesa '),
        ));
        self::assertCount(3, $apuntesRemesa);
        self::assertEqualsCanonicalizing(['111', '22', '9'], array_column($apuntesRemesa, 'concepto_codigo'));
        $porConcepto = [];
        foreach ($apuntesRemesa as $a) {
            $porConcepto[$a['concepto_codigo']] = $a['cantidad'];
        }
        self::assertSame('12.50', $porConcepto['111']);
        self::assertSame('37.50', $porConcepto['9']);
        $e37 = $d['asientos']->movimientosE37PorPersona(
            $d['centroId'],
            $d['ejercicioId'],
            sprintf('%04d-01-01', $anio),
            sprintf('%04d-01-31', $anio),
        );
        self::assertSame(1250, $e37['aa']['22'] ?? 0);
        self::assertSame(5000, $e37['aa']['111'] ?? 0);

        $ord = $d['cuentas']->buscar($d['centroId'], $d['personaId'], 'X', '22');
        self::assertNotNull($ord?->id);
        $d['registrar']->ejecutar([
            'sentido' => 'gasto',
            'fecha' => sprintf('%04d-01-20', $anio),
            'cantidad' => '10.00',
            'cuenta_id' => $ord->id,
            'tesoreria' => 'CAJA',
        ]);
        $v3 = $d['enviar']->ejecutar(['anio' => $anio, 'mes' => 1]);
        self::assertSame(3, $v3->version);
        $d['aceptar']->ejecutar((int) $v3->id);
        self::assertSame(1, $this->contarAsientosRemesa(), 'Aceptar no puede duplicar asientos de remesa');
        self::assertSame(2250, $this->realizado22($d));

        $aceptada = $d['remesas']->porId((int) $v3->id);
        self::assertNotNull($aceptada);
        $linea22 = null;
        foreach ($aceptada->lineas as $l) {
            if ($l->codigoMaestro === '22') {
                $linea22 = $l;
            }
        }
        self::assertNotNull($linea22?->id);
        $sol = $d['solicitar']->ejecutar((int) $aceptada->id, $linea22->id);
        $d['resolverSol']->ejecutar((int) $sol->id, ['estado' => 'autorizada']);
        $detalle = $d['detalle']->ejecutar((int) $aceptada->id, $linea22->id);
        $codigosDet = array_column($detalle['detalle'], 'codigo');
        self::assertContains('22.gas', $codigosDet);

        $asientoId = (int) $this->pdo->query(
            "SELECT id FROM asientos WHERE origen = 'remesa' AND anulado_at IS NULL"
        )->fetchColumn();
        try {
            $d['borrar']->ejecutar($asientoId);
            self::fail('Se debió impedir borrar un asiento de remesa');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('remesa', $e->getMessage());
        }

        self::assertSame($cajaAntes, $d['saldos']->ejecutar($fecha)['caja']);
    }

    private function contarAsientosRemesa(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM asientos WHERE origen = 'remesa' AND anulado_at IS NULL"
        )->fetchColumn();
    }

    /** @param array<string, mixed> $d */
    private function realizado22(array $d): int
    {
        $real = $d['asientos']->realizadoPorConcepto(
            $d['centroId'],
            $d['ejercicioId'],
            'P',
            sprintf('%04d-01-01', $d['anio']),
            sprintf('%04d-01-31', $d['anio']),
        );

        return (int) ($real['22'] ?? 0);
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
        $remesas = new PdoRemesaRepository($this->pdo);
        $identidades = new PdoIdentidadRepository($this->pdo);
        $asegurar = new AsegurarPlanPersonal($cuentas);
        $ambitoCentro = new ResolverAmbitoActual($config, $centros, $ejercicios);
        $ctx = $ambitoCentro->ejecutar();
        $centroId = $ctx->centroId;
        $anio = (int) $config->get()->anio;

        $persona = $personas->guardar(new Persona(
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
        self::assertNotNull($persona->id);
        AmbitoSeeder::sembrar($this->pdo);
        AccesoSeeder::sembrar($this->pdo);
        Nivel1Seeder::sembrar($this->pdo);

        $yo = $identidades->porEmailOAlias('yo');
        self::assertNotNull($yo?->id);
        $scl = $identidades->porEmailOAlias('scl');
        self::assertNotNull($scl?->id);

        $resolver = new ResolverPersonaActual(
            $identidades,
            $personas,
            $ejercicios,
            $asegurar,
            $yo->id,
            $persona->id,
        );
        $cierres = new PdoPersonalCierreRepository($this->pdo);
        $periodoPersonal = new ResolverPeriodoPersonal($cierres);
        $mes = new ResolverMesRemesa($resolver, $ejercicios, $asientos, $cuentas, $periodoPersonal);
        $conceptos = new PdoConceptoRepository($this->pdo);
        $gastosGenerales = $this->gastosGeneralesDeRemesa(
            $asientos,
            $conceptos,
            $personas,
            $config,
            $cuentas,
            $ambitoCentro,
            $ejercicios,
        );

        $saldosDisp = new \src\disponible\infrastructure\persistence\PdoSaldoDisponibleRepository($this->pdo);
        $asigRepo = new \src\disponible\infrastructure\persistence\PdoAsignacionLaboresRepository($this->pdo);
        $disponible = new \src\disponible\application\AplicarDisponibleDeRemesa(
            $cuentas,
            $asientos,
            $saldosDisp,
            $asigRepo,
            $personas,
            new \src\ambito\application\AsegurarCuentaDisponiblePersona($cuentas),
        );

        return [
            'centroId' => $centroId,
            'ejercicioId' => $ctx->ejercicioId,
            'anio' => $anio,
            'personaId' => $persona->id,
            'cuentas' => $cuentas,
            'asientos' => $asientos,
            'remesas' => $remesas,
            'registrar' => new RegistrarMovimientoPersonal(
                $resolver,
                $cuentas,
                $ejercicios,
                $asientos,
                $personas,
                new \src\personal\domain\services\ResolverCategoriaPlantillaPersonal(
                    new \src\apuntes\infrastructure\persistence\PdoPlantillaApunteRepository($this->pdo),
                    $cuentas,
                ),
            ),
            'subcuenta' => new CrearSubcuentaPersonal($resolver, $cuentas),
            'preview' => new PrevisualizarRemesa(
                $mes,
                $remesas,
                $personas,
                $periodoPersonal,
                new \src\remesas\application\EnriquecerLineasRemesa(
                    new \src\apuntes\infrastructure\persistence\PdoPlantillaApunteRepository($this->pdo),
                ),
            ),
            'enviar' => new EnviarRemesa($mes, $remesas),
            'aceptar' => new AceptarRemesa(
                $ambitoCentro,
                $remesas,
                $asientos,
                $cuentas,
                $ejercicios,
                $personas,
                $periodoPersonal,
                $gastosGenerales,
                $this->plantillasDeRemesa($asientos, $conceptos, $personas, $config, $cuentas, $ambitoCentro, $ejercicios),
                $disponible,
            ),
            'rechazar' => new RechazarRemesa($ambitoCentro, $remesas, $asientos, $disponible),
            'solicitar' => new SolicitarDetalleRemesa($ambitoCentro, $remesas, $scl->id),
            'resolverSol' => new ResolverSolicitudDetalle($resolver, $remesas),
            'detalle' => new ObtenerDetalleRemesa(
                $ambitoCentro,
                $remesas,
                new \src\remesas\application\EnriquecerLineasRemesa(
                    new \src\apuntes\infrastructure\persistence\PdoPlantillaApunteRepository($this->pdo),
                ),
            ),
            'borrar' => new BorrarApunte($asientos),
            'listar' => new ListarApuntes(
                $asientos,
                $cuentas,
                $personas,
                new ProyectorAsientoAFilaExcel(),
                $ambitoCentro,
            ),
            'saldos' => new CalcularSaldos($asientos, $config, $personas, $ambitoCentro),
        ];
    }

    private function gastosGeneralesDeRemesa(
        PdoAsientoRepository $asientos,
        PdoConceptoRepository $conceptos,
        PdoPersonaRepository $personas,
        PdoConfiguracionRepository $config,
        PdoCuentaRepository $cuentas,
        ResolverAmbitoActual $ambito,
        PdoEjercicioRepository $ejercicios,
    ): RegistrarGastosGeneralesDeRemesa {
        $crear = new CrearApuntesDeEntrada(
            new CrearApunte(
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
            ),
            $conceptos,
            $personas,
            new ContrapartidasGastoGeneral(),
        );

        return new RegistrarGastosGeneralesDeRemesa($crear, $personas);
    }

    private function plantillasDeRemesa(
        PdoAsientoRepository $asientos,
        PdoConceptoRepository $conceptos,
        PdoPersonaRepository $personas,
        PdoConfiguracionRepository $config,
        PdoCuentaRepository $cuentas,
        ResolverAmbitoActual $ambito,
        PdoEjercicioRepository $ejercicios,
    ): RegistrarPlantillasDeRemesa {
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
        );

        return new RegistrarPlantillasDeRemesa(
            $crearApunte,
            new \src\apuntes\infrastructure\persistence\PdoPlantillaApunteRepository($this->pdo),
            $personas,
        );
    }
}
