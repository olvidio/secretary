<?php

declare(strict_types=1);

namespace Tests\integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\apuntes\application\ListarApuntes;
use src\apuntes\infrastructure\persistence\PdoApunteRepository;
use src\arqueo\domain\contracts\ArqueoRepository;
use src\arqueo\infrastructure\persistence\PdoArqueoRepository;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\conceptos\application\ListarConceptos;
use src\conceptos\infrastructure\persistence\PdoConceptoRepository;
use src\configuracion\application\ObtenerConfiguracion;
use src\configuracion\infrastructure\persistence\PdoConfiguracionRepository;
use src\importacion\application\ImportarExcelSecretario;
use src\informes\application\CalcularSaldos;
use src\informes\application\ObtenerE37;
use src\informes\application\ObtenerResumen613;
use src\personas\application\ListarPersonas;
use src\personas\infrastructure\persistence\PdoPersonaRepository;
use src\presupuestos\domain\contracts\PresupuestoRepository;
use src\presupuestos\infrastructure\persistence\PdoPresupuestoRepository;
use src\shared\infrastructure\persistence\SchemaInstaller;
use Tests\Soporte\BaseDeDatosAislada;

/**
 * Fase 0 del plan de ampliaciones (docs/dev/plan_ampliaciones.md): red de seguridad.
 *
 * Importa moviments2026.xlsm contra una base Postgres aislada y compara TODOS los
 * informes con los ficheros de referencia en tests/golden/. Ninguna fase posterior
 * puede modificar ese resultado sin documentar y justificar la diferencia.
 *
 * Regenerar el golden master (tras un cambio intencionado):
 *   GOLDEN_UPDATE=1 vendor/bin/phpunit tests/integration/GoldenMasterTest.php
 *
 * Ver docs/dev/golden_master.md.
 */
final class GoldenMasterTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DB_NAME_TEST = 'secretario_test';

    /** @var list<string> */
    private array $informesConDiferencias = [];

    /** @var array{nombre:string,esperado:string,actual:string}|null */
    private ?array $primeraDiferencia = null;

    public function testImportacionYInformesCoincidenConElGoldenMaster(): void
    {
        $this->saltarSiNoHayPgsql();

        $excelPath = dirname(__DIR__, 2) . '/moviments2026.xlsm';
        if (!is_readable($excelPath)) {
            self::markTestSkipped(
                'Falta moviments2026.xlsm en la raíz del repositorio; no se puede generar ni comparar el golden master.'
            );
        }

        try {
            $pdo = $this->prepararBaseDeTestVacia(self::DB_NAME_TEST);
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo preparar la base de datos de test aislada: ' . $e->getMessage());
        }

        (new SchemaInstaller($pdo))->install();

        $configRepo = new PdoConfiguracionRepository($pdo);
        $personaRepo = new PdoPersonaRepository($pdo);
        $conceptoRepo = new PdoConceptoRepository($pdo);
        $apunteRepo = new PdoApunteRepository($pdo);
        $presupuestoRepo = new PdoPresupuestoRepository($pdo);
        $arqueoRepo = new PdoArqueoRepository($pdo);
        $asientoRepo = new PdoAsientoRepository($pdo);
        $cuentaRepo = new PdoCuentaRepository($pdo);
        $centroRepo = new PdoCentroRepository($pdo);
        $ejercicioRepo = new PdoEjercicioRepository($pdo);
        $ambito = new ResolverAmbitoActual($configRepo, $centroRepo, $ejercicioRepo);
        $proyector = new ProyectorAsientoAFilaExcel();
        $listarApuntes = new ListarApuntes($asientoRepo, $cuentaRepo, $personaRepo, $proyector, $ambito);

        $importar = new ImportarExcelSecretario(
            $pdo,
            $configRepo,
            $personaRepo,
            $conceptoRepo,
            $apunteRepo,
            $presupuestoRepo,
        );
        $resultadoImportacion = $importar->ejecutar($excelPath, true);

        $this->compararOActualizar('importacion', $resultadoImportacion);
        $this->compararOActualizar('configuracion', (new ObtenerConfiguracion($configRepo))->ejecutar());
        $this->compararOActualizar('personas', (new ListarPersonas($personaRepo))->ejecutar());
        $this->compararOActualizar('conceptos_p', (new ListarConceptos($conceptoRepo))->ejecutar('P'));
        $this->compararOActualizar('conceptos_g', (new ListarConceptos($conceptoRepo))->ejecutar('G'));
        $this->compararOActualizar('presupuesto_p', $this->dumpPresupuesto($presupuestoRepo, 'P'));
        $this->compararOActualizar('presupuesto_g', $this->dumpPresupuesto($presupuestoRepo, 'G'));

        $resumen613 = new ObtenerResumen613($configRepo, $asientoRepo, $presupuestoRepo, $personaRepo, $ambito);
        $this->compararOActualizar('resumen_613_p', $resumen613->ejecutar('P'));
        $this->compararOActualizar('resumen_613_g', $resumen613->ejecutar('G'));

        $e37 = new ObtenerE37($configRepo, $personaRepo, $asientoRepo, $listarApuntes, $ambito);
        $this->compararOActualizar('e37', $e37->detalle(null));
        $this->compararOActualizar('e37_resumen', $e37->resumen());

        $saldos = new CalcularSaldos($asientoRepo, $configRepo, $personaRepo, $ambito);
        $this->compararOActualizar('saldos', $saldos->ejecutar(null));

        $this->compararOActualizar('arqueo', $this->dumpArqueos($arqueoRepo));
        $this->compararOActualizar('apuntes_agregado', $this->dumpAgregadoApuntes($pdo));

        if ($this->informesConDiferencias !== [] && $this->primeraDiferencia !== null) {
            self::assertSame(
                $this->primeraDiferencia['esperado'],
                $this->primeraDiferencia['actual'],
                sprintf(
                    "Informes que difieren del golden master: %s (diff mostrado de '%s'). ".
                    'Si el cambio es intencionado y está documentado, regenera con GOLDEN_UPDATE=1.',
                    implode(', ', $this->informesConDiferencias),
                    $this->primeraDiferencia['nombre'],
                )
            );
        }
        self::assertSame([], $this->informesConDiferencias);
    }

    /** @return list<array<string, mixed>> */
    private function dumpPresupuesto(PresupuestoRepository $repo, string $cuenta): array
    {
        $out = [];
        foreach ($repo->listar($cuenta) as $linea) {
            $out[] = $linea->toArray();
        }
        usort($out, static fn (array $a, array $b): int => $a['concepto_codigo'] <=> $b['concepto_codigo']);

        return $out;
    }

    /** @return array{p: array<string, mixed>|null, g: array<string, mixed>|null} */
    private function dumpArqueos(ArqueoRepository $repo): array
    {
        return [
            'p' => $repo->ultimo('P')?->toArray(),
            'g' => $repo->ultimo('G')?->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dumpAgregadoApuntes(PDO $pdo): array
    {
        $total = $pdo->query('SELECT COUNT(*) AS n, COALESCE(SUM(cantidad::numeric(14,2)), 0)::text AS suma FROM apuntes')
            ->fetch();

        $porOrigen = $pdo->query(
            'SELECT cuenta, origen, COUNT(*) AS n, SUM(cantidad::numeric(14,2))::text AS suma
             FROM apuntes GROUP BY cuenta, origen ORDER BY cuenta, origen'
        )->fetchAll();

        $porConcepto = $pdo->query(
            'SELECT cuenta, concepto_codigo, COUNT(*) AS n, SUM(cantidad::numeric(14,2))::text AS suma
             FROM apuntes GROUP BY cuenta, concepto_codigo ORDER BY cuenta, concepto_codigo'
        )->fetchAll();

        $porIniciales = $pdo->query(
            "SELECT cuenta, COALESCE(iniciales, '') AS iniciales, COUNT(*) AS n, SUM(cantidad::numeric(14,2))::text AS suma
             FROM apuntes GROUP BY cuenta, COALESCE(iniciales, '') ORDER BY cuenta, COALESCE(iniciales, '')"
        )->fetchAll();

        return [
            'total_apuntes' => (int) $total['n'],
            'suma_total' => (string) $total['suma'],
            'por_cuenta_origen' => array_map(self::normalizarFilaAgregado(...), $porOrigen),
            'por_cuenta_concepto' => array_map(self::normalizarFilaAgregado(...), $porConcepto),
            'por_cuenta_iniciales' => array_map(self::normalizarFilaAgregado(...), $porIniciales),
        ];
    }

    /** @param array<string, mixed> $fila @return array<string, mixed> */
    private static function normalizarFilaAgregado(array $fila): array
    {
        $fila['n'] = (int) $fila['n'];
        $fila['suma'] = (string) $fila['suma'];

        return $fila;
    }

    /** @param array<string, mixed> $datos */
    private function compararOActualizar(string $nombre, array $datos): void
    {
        $json = json_encode(self::ordenarClaves($datos), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('No se pudo serializar el informe ' . $nombre);
        }
        $json .= "\n";

        $path = self::goldenDir() . '/' . $nombre . '.json';
        $actualizar = (getenv('GOLDEN_UPDATE') === '1') || (($_ENV['GOLDEN_UPDATE'] ?? '') === '1');

        if ($actualizar || !is_file($path)) {
            file_put_contents($path, $json);

            return;
        }

        $esperado = (string) file_get_contents($path);
        if ($esperado !== $json) {
            $this->informesConDiferencias[] = $nombre;
            $this->primeraDiferencia ??= ['nombre' => $nombre, 'esperado' => $esperado, 'actual' => $json];
        }
    }

    private static function goldenDir(): string
    {
        $dir = dirname(__DIR__) . '/golden';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear ' . $dir);
        }

        return $dir;
    }

    private static function ordenarClaves(mixed $valor): mixed
    {
        if (is_float($valor)) {
            $r = round($valor, 6);

            return $r === 0.0 ? 0.0 : $r;
        }
        if (!is_array($valor)) {
            return $valor;
        }
        if (array_is_list($valor)) {
            return array_map(self::ordenarClaves(...), $valor);
        }
        ksort($valor);
        $out = [];
        foreach ($valor as $k => $v) {
            $out[$k] = self::ordenarClaves($v);
        }

        return $out;
    }
}
