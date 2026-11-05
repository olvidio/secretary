<?php

declare(strict_types=1);

namespace Tests\integration;

use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaFisicaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\arqueo\infrastructure\persistence\PdoArqueoRepository;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\informes\application\ObtenerResumen613;
use src\informes\infrastructure\persistence\PdoInforme613MesRepository;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\plan\infrastructure\persistence\PdoPartidaLaboresRepository;
use src\plan\infrastructure\persistence\PdoPlanConceptoRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\remesas\application\BorrarSaldoCcManual;
use src\remesas\application\ListarSaldosCcManuales;
use src\remesas\application\RegistrarSaldoCcManual;
use src\remesas\infrastructure\persistence\PdoRemesaRepository;
use src\remesas\infrastructure\persistence\PdoSaldoCcManualRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/** Concepto 90 anotado a mano por el secretario (personas sin remesa). */
final class SaldoCcManualTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME = 'secretario_test_saldo_cc_manual';

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

    public function testRegistrarSustituirListarYBorrar(): void
    {
        $d = $this->deps();
        $guardado = $d['registrar']->ejecutar([
            'persona_id' => $d['personaId'],
            'anio' => $d['anio'],
            'mes' => $d['mes'],
            'saldo' => '1.234,56',
            'nota' => 'Comunicado por teléfono',
        ]);
        self::assertSame(123456, $guardado->saldoCents);

        $d['registrar']->ejecutar([
            'persona_id' => $d['personaId'],
            'anio' => $d['anio'],
            'mes' => $d['mes'],
            'saldo' => '-50',
        ]);
        $lista = $d['listar']->ejecutar();
        self::assertCount(1, $lista);
        self::assertSame(-5000, $lista[0]['saldo_cents']);
        self::assertNull($lista[0]['nota']);
        self::assertSame('bb', $lista[0]['iniciales']);

        $d['borrar']->ejecutar((int) $lista[0]['id']);
        self::assertSame([], $d['listar']->ejecutar());
    }

    public function testMesFueraDelEjercicioSeRechaza(): void
    {
        $d = $this->deps();
        $this->expectException(InvalidArgumentException::class);
        $d['registrar']->ejecutar([
            'persona_id' => $d['personaId'],
            'anio' => $d['anio'] + 5,
            'mes' => 1,
            'saldo' => '10',
        ]);
    }

    public function testPersonaDeOtroCentroSeRechaza(): void
    {
        $d = $this->deps();
        $this->expectException(InvalidArgumentException::class);
        $d['registrar']->ejecutar([
            'persona_id' => 999999,
            'anio' => $d['anio'],
            'mes' => $d['mes'],
            'saldo' => '10',
        ]);
    }

    public function testElSaldoManualSumaEnLaLinea90Del613P(): void
    {
        $d = $this->deps();
        self::assertSame('0.00', $this->linea90($d['resumen']->ejecutar('P')));

        $d['registrar']->ejecutar([
            'persona_id' => $d['personaId'],
            'anio' => $d['anio'],
            'mes' => $d['mes'],
            'saldo' => '321,00',
        ]);
        self::assertSame('321.00', $this->linea90($d['resumen']->ejecutar('P')));
    }

    /** @param array<string, mixed> $payload */
    private function linea90(array $payload): ?string
    {
        foreach ($payload['lineas'] ?? [] as $linea) {
            if (($linea['codigo'] ?? null) === '90') {
                return $linea['realizado'];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function deps(): array
    {
        $config = new PdoConfiguracionRepository($this->pdo);
        $centros = new PdoCentroRepository($this->pdo);
        $ejercicios = new PdoEjercicioRepository($this->pdo);
        $personas = new PdoPersonaRepository($this->pdo);
        $ambito = new ResolverAmbitoActual($config, $centros, $ejercicios);
        $ctx = $ambito->ejecutar();
        $persona = $personas->guardar(new Persona(
            null, 'Berta', 'Beta', 'bb', null, null, null, null, null, 1, $ctx->centroId,
        ));
        self::assertNotNull($persona->id);
        AmbitoSeeder::sembrar($this->pdo);

        $repo = new PdoSaldoCcManualRepository($this->pdo);
        $corte = $config->get()->fechaCierre;

        return [
            'personaId' => $persona->id,
            'anio' => (int) $corte->format('Y'),
            'mes' => (int) $corte->format('n'),
            'registrar' => new RegistrarSaldoCcManual($ambito, $ejercicios, $personas, $repo),
            'listar' => new ListarSaldosCcManuales($ambito, $personas, $repo),
            'borrar' => new BorrarSaldoCcManual($ambito, $repo),
            'resumen' => new ObtenerResumen613(
                $config,
                new PdoAsientoRepository($this->pdo),
                new PdoPresupuestoRepository($this->pdo),
                $personas,
                $ambito,
                new PdoPartidaLaboresRepository($this->pdo, new PdoPlanConceptoRepository($this->pdo)),
                new PdoInforme613MesRepository($this->pdo),
                new PdoArqueoRepository($this->pdo),
                new PdoCuentaFisicaRepository($this->pdo),
                null,
                null,
                null,
                null,
                null,
                new PdoRemesaRepository($this->pdo),
                $repo,
            ),
        ];
    }
}
