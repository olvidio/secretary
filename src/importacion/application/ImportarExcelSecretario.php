<?php

declare(strict_types=1);

namespace src\importacion\application;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use src\apuntes\domain\contracts\ApunteRepository;
use src\apuntes\domain\entity\Apunte;
use src\conceptos\domain\contracts\ConceptoRepository;
use src\conceptos\domain\entity\Concepto;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;
use src\importacion\domain\entity\FilaOrigenExcel;
use src\importacion\infrastructure\excel\XlsxReader;
use src\importacion\infrastructure\persistence\PdoImportEjecucionRepository;
use src\importacion\infrastructure\persistence\PdoImportFilaRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;
use src\presupuestos\domain\contracts\PresupuestoRepository;
use src\presupuestos\domain\entity\LineaPresupuesto;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Ejercicio;
use src\ambito\infrastructure\persistence\AmbitoSeeder;
use src\ambito\infrastructure\persistence\PdoCentroRepository;
use src\ambito\infrastructure\persistence\PdoCuentaRepository;
use src\ambito\infrastructure\persistence\PdoEjercicioRepository;
use src\asientos\domain\services\TraductorApuntesAAsientos;
use src\asientos\infrastructure\persistence\PdoAsientoRepository;
use src\personal\infrastructure\persistence\Nivel1Seeder;
use src\shared\domain\value_objects\Dinero;
use src\shared\infrastructure\excel\ExcelDate;

final class ImportarExcelSecretario
{
    public const HOJA_TALONARIOS = 'Talonarios';

    /** @var array<string, mixed> */
    private array $ultimoInforme = [];

    public function __construct(
        private readonly PDO $pdo,
        private readonly ConfiguracionRepository $config,
        private readonly PersonaRepository $personas,
        private readonly ConceptoRepository $conceptos,
        private readonly ApunteRepository $apuntes,
        private readonly PresupuestoRepository $presupuesto,
        private readonly ?SincronizarAsientosImportados $sincronizar = null,
        private readonly ?EjercicioRepository $ejercicios = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function ultimoInforme(): array
    {
        return $this->ultimoInforme;
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        string $path,
        bool $reemplazar = true,
        bool $dryRun = false,
        ?string $centroCodigo = null,
        ?string $ejercicioEtiqueta = null,
    ): array {
        $book = new XlsxReader($path);
        $sha256 = hash_file('sha256', $path);
        if ($sha256 === false) {
            throw new RuntimeException('No se pudo calcular el sha256 de ' . $path);
        }
        $filasExcel = $this->leerFilasTalonarios($book);
        $cfgLeida = $this->leerConfig($book);
        $aislado = $centroCodigo !== null && trim($centroCodigo) !== '';

        if ($dryRun) {
            $nApuntes = count($filasExcel);
            if (!$aislado) {
                AmbitoSeeder::sembrar($this->pdo);
            }
            $destino = $this->resolverDestino($centroCodigo, $ejercicioEtiqueta, $cfgLeida);
            $stats = $this->sincronizador()->ejecutar(
                $destino['centro_id'],
                $destino['ejercicio_id'],
                basename($path),
                $sha256,
                $filasExcel,
                true,
            );
            $this->ultimoInforme = $stats;

            return [
                'centro' => $cfgLeida->centro,
                'anio' => $cfgLeida->anio,
                'personas' => 0,
                'entidades' => 0,
                'lineas_presupuesto' => 0,
                'apuntes' => $nApuntes,
                'asientos' => $stats['asientos'],
            ];
        }

        $nPersonas = 0;
        $nEntidades = 0;
        $nPresu = 0;
        $nApuntes = 0;
        $stats = [
            'asientos' => 0,
            'omitidos_concepto_9' => 0,
            'traspasos_fusionados' => 0,
            'altas' => 0,
            'cambios' => 0,
            'bajas' => 0,
            'saltados' => 0,
        ];
        $this->pdo->beginTransaction();
        try {
            if (!$aislado) {
                $this->config->guardar($cfgLeida);
                if ($reemplazar) {
                    // Instantánea de `apuntes` y presupuesto para el golden master y el
                    // listado legado. El libro diario (asientos) ya no se borra: D8.
                    $this->apuntes->borrarTodos();
                    $this->presupuesto->borrarCuenta('P');
                    $this->presupuesto->borrarCuenta('G');
                }
                AmbitoSeeder::sembrar($this->pdo);
            }
            $destino = $this->resolverDestino($centroCodigo, $ejercicioEtiqueta, $cfgLeida);
            if ($aislado) {
                $this->alinearEjercicioAbierto($destino['centro_id'], $cfgLeida);
            }
            $nPersonas = $this->importarPersonas($book, $reemplazar, $destino['centro_id']);
            AmbitoSeeder::poblarLibros($this->pdo, $destino['centro_id']);
            if (!$aislado) {
                $nEntidades = $this->importarEntidades($book);
                $nPresu = $this->importarPresupuestos($book);
                $nApuntes = $this->persistirApuntes($filasExcel);
            }
            Nivel1Seeder::sembrar($this->pdo);
            $stats = $this->sincronizador()->ejecutar(
                $destino['centro_id'],
                $destino['ejercicio_id'],
                basename($path),
                $sha256,
                $filasExcel,
                false,
            );
            $this->ultimoInforme = $stats;
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'centro' => $cfgLeida->centro,
            'anio' => $cfgLeida->anio,
            'personas' => $nPersonas,
            'entidades' => $nEntidades,
            'lineas_presupuesto' => $nPresu,
            'apuntes' => $nApuntes,
            'asientos' => $stats['asientos'],
        ];
    }

    private function sincronizador(): SincronizarAsientosImportados
    {
        return $this->sincronizar ?? new SincronizarAsientosImportados(
            new PdoAsientoRepository($this->pdo),
            new PdoCuentaRepository($this->pdo),
            $this->personas,
            new TraductorApuntesAAsientos(),
            new PdoImportFilaRepository($this->pdo),
            new PdoImportEjecucionRepository($this->pdo),
        );
    }

    /**
     * @return array{centro_id: int, ejercicio_id: int}
     */
    private function resolverDestino(
        ?string $centroCodigo,
        ?string $ejercicioEtiqueta,
        ConfiguracionCentro $cfg,
    ): array {
        $centroRepo = new PdoCentroRepository($this->pdo);
        $ejercicioRepo = $this->repoEjercicios();
        $codigo = $centroCodigo !== null && $centroCodigo !== ''
            ? $centroCodigo
            : trim($cfg->centro);
        $centro = $codigo !== '' ? $centroRepo->porCodigo($codigo) : null;
        $centro ??= $centroRepo->listar()[0] ?? null;
        if ($centro?->id === null) {
            throw new RuntimeException('No hay centro para importar');
        }
        $ejercicio = null;
        if ($ejercicioEtiqueta !== null && $ejercicioEtiqueta !== '') {
            foreach ($ejercicioRepo->listarDeCentro($centro->id) as $candidato) {
                if (strcasecmp($candidato->etiqueta, $ejercicioEtiqueta) === 0) {
                    $ejercicio = $candidato;
                    break;
                }
                if (ctype_digit($ejercicioEtiqueta)
                    && (int) $candidato->fechaInicio->format('Y') === (int) $ejercicioEtiqueta
                ) {
                    $ejercicio = $candidato;
                    break;
                }
            }
            if ($ejercicio === null) {
                throw new InvalidArgumentException('Ejercicio no encontrado: ' . $ejercicioEtiqueta);
            }
        } else {
            $ejercicio = $ejercicioRepo->abiertoDe($centro->id);
        }
        if ($ejercicio?->id === null) {
            throw new RuntimeException('No hay ejercicio abierto para importar');
        }

        return ['centro_id' => $centro->id, 'ejercicio_id' => $ejercicio->id];
    }

    /**
     * Ajusta el ejercicio abierto del centro destino a las fechas del Excel,
     * sin tocar el singleton `configuracion` (otro centro puede estar usándolo).
     */
    private function alinearEjercicioAbierto(int $centroId, ConfiguracionCentro $cfg): void
    {
        $ejercicio = $this->repoEjercicios()->abiertoDe($centroId);
        if ($ejercicio === null || $ejercicio->id === null) {
            return;
        }
        $periodo = $cfg->periodo();
        $corte = $periodo->fechaCorte;
        if ($corte < $periodo->fechaInicio) {
            $corte = $periodo->fechaInicio;
        }
        if ($corte > $periodo->fechaFin) {
            $corte = $periodo->fechaFin;
        }
        $etiqueta = $cfg->modoEjercicio === 'Curso'
            ? sprintf('%d-%02d', $cfg->anio, ($cfg->anio + 1) % 100)
            : (string) $cfg->anio;
        $this->repoEjercicios()->guardar(new Ejercicio(
            $ejercicio->id,
            $ejercicio->centroId,
            $etiqueta,
            $periodo->fechaInicio,
            $periodo->fechaFin,
            $corte,
            $ejercicio->estado,
            $ejercicio->ejercicioAnteriorId,
        ));
    }

    private function repoEjercicios(): EjercicioRepository
    {
        return $this->ejercicios ?? new PdoEjercicioRepository($this->pdo);
    }

    private function leerConfig(XlsxReader $book): ConfiguracionCentro
    {
        $conf = $book->sheet('Configuración');
        $conceptos = $book->sheet('Conceptos');
        $centro = trim((string) ($conf[1]['B'] ?? 'Centro'));
        $anio = (int) ($conf[2]['B'] ?? date('Y'));
        $modo = trim((string) ($conceptos[9]['H'] ?? 'Año'));
        if (!in_array($modo, ['Año', 'Curso'], true)) {
            $modo = 'Año';
        }
        $ini = ExcelDate::parseCell($conceptos[6]['H'] ?? null)
            ?? new DateTimeImmutable(sprintf('%d-01-01', $anio));
        $cie = ExcelDate::parseCell($conceptos[3]['H'] ?? null)
            ?? new DateTimeImmutable(sprintf('%d-01-31', $anio));
        $num = isset($conceptos[21]['H']) && is_numeric($conceptos[21]['H'])
            ? (int) $conceptos[21]['H'] : null;
        $ver = isset($conceptos[3]['O']) ? (string) $conceptos[3]['O'] : '8';
        $tipo = ConfiguracionCentro::tipoCierreDesdeCentro($centro);
        $cfg = new ConfiguracionCentro(
            $centro,
            $anio,
            $modo,
            $ini,
            $cie,
            $tipo,
            $num,
            $ver,
            null,
            null,
            null,
            null,
            null,
        );

        return $cfg;
    }

    /**
     * Upsert por `(centro_id, iniciales)` (Fase 2b / Fase 9): busca la persona
     * existente en ese centro y, si existe, actualiza sobre su `id` en vez de
     * recrearla, para no romper la FK `cuentas.persona_id`. Al terminar, si
     * `$reemplazar` es cierto, sincroniza `activo` solo en ese centro.
     */
    private function importarPersonas(XlsxReader $book, bool $reemplazar, int $centroId): int
    {
        $sheet = $book->sheet('Nombres P');
        $n = 0;
        $inicialesPresentes = [];
        foreach ($sheet as $row => $cols) {
            if ($row < 2) {
                continue;
            }
            $nombre = trim((string) ($cols['B'] ?? ''));
            $iniciales = strtolower(trim((string) ($cols['D'] ?? '')));
            if ($nombre === '' || $iniciales === '') {
                continue;
            }
            $fijo = null;
            if (!empty($cols['K']) && is_numeric($cols['K'])) {
                $fijo = new Dinero(number_format((float) $cols['K'], 2, '.', ''));
            }
            $existente = $this->personas->porInicialesDeCentro($centroId, $iniciales);
            $this->personas->guardar(new Persona(
                $existente?->id,
                $nombre,
                trim((string) ($cols['C'] ?? '')),
                $iniciales,
                self::mesInt($cols['E'] ?? null),
                self::mesInt($cols['F'] ?? null),
                self::mesInt($cols['H'] ?? null),
                self::mesInt($cols['I'] ?? null),
                $fijo,
                $n + 1,
                $centroId,
                $existente->activo ?? true,
                $existente->email ?? null,
            ));
            $inicialesPresentes[] = $iniciales;
            $n++;
        }

        if ($reemplazar) {
            $this->personas->sincronizarActivos($inicialesPresentes, $centroId);
        }

        return $n;
    }

    private function importarEntidades(XlsxReader $book): int
    {
        $presu = $book->sheet('Presupuesto P');
        $n = 0;
        $map = [33 => '72', 34 => '73', 35 => '74', 36 => '75', 37 => '76', 38 => '77', 39 => '78', 40 => '79'];
        foreach ($map as $row => $codigo) {
            $nombre = trim((string) ($presu[$row]['C'] ?? ''));
            $actual = $this->conceptos->buscar('P', $codigo);
            if ($actual === null) {
                continue;
            }
            $this->conceptos->guardar(new Concepto(
                $codigo,
                'P',
                $nombre !== '' ? $nombre : $actual->nombre,
                $codigo . ' ' . ($nombre !== '' ? $nombre : $actual->nombre),
                $actual->naturaleza,
                $actual->orden,
            ));
            $n++;
        }

        return $n;
    }

    private function importarPresupuestos(XlsxReader $book): int
    {
        $n = 0;
        $pMap = [
            6 => '111', 7 => '112', 8 => '113', 9 => '12',
            12 => '21', 13 => '22', 14 => '23', 15 => '24', 16 => '25', 17 => '26', 18 => '27', 19 => '28',
            23 => '4', 26 => '51', 27 => '52', 29 => '6',
            32 => '71', 33 => '72', 34 => '73', 35 => '74', 36 => '75', 37 => '76', 38 => '77', 39 => '78', 40 => '79',
        ];
        $pSheet = $book->sheet('Presupuesto P');
        foreach ($pMap as $row => $cod) {
            $val = $pSheet[$row]['F'] ?? null;
            if ($val === null || $val === '') {
                continue;
            }
            $this->presupuesto->guardar(new LineaPresupuesto('P', $cod, new Dinero(number_format((float) $val, 2, '.', ''))));
            $n++;
        }
        $gMap = [
            5 => '11', 6 => '12', 7 => '13', 8 => '14', 9 => '15',
            13 => '201', 14 => '202', 15 => '203', 16 => '204', 17 => '205', 18 => '206', 19 => '207',
            20 => '208', 21 => '209', 22 => '210', 23 => '211', 24 => '212', 25 => '213', 26 => '214', 27 => '215',
            32 => '32',
        ];
        $gSheet = $book->sheet('Presupuesto G');
        foreach ($gMap as $row => $cod) {
            $val = $gSheet[$row]['F'] ?? null;
            if ($val === null || $val === '') {
                continue;
            }
            $this->presupuesto->guardar(new LineaPresupuesto('G', $cod, new Dinero(number_format((float) $val, 2, '.', ''))));
            $n++;
        }

        return $n;
    }

    /**
     * @return list<FilaOrigenExcel>
     */
    private function leerFilasTalonarios(XlsxReader $book): array
    {
        $sheet = $book->sheet(self::HOJA_TALONARIOS);
        $filas = [];
        foreach ($sheet as $row => $cols) {
            if ($row < 2) {
                continue;
            }
            $fecha = ExcelDate::parseCell($cols['A'] ?? null);
            $cuenta = strtoupper(trim((string) ($cols['B'] ?? '')));
            $origen = strtoupper(trim((string) ($cols['C'] ?? '')));
            $concepto = trim((string) ($cols['E'] ?? ''));
            $cant = $cols['G'] ?? null;
            if ($fecha === null || !in_array($cuenta, ['P', 'G'], true) || !in_array($origen, ['A', 'B', 'C'], true) || $concepto === '' || $cant === null || $cant === '') {
                continue;
            }
            $iniciales = strtolower(trim((string) ($cols['D'] ?? '')));
            $obs = isset($cols['F']) ? trim((string) $cols['F']) : null;
            $filas[] = new FilaOrigenExcel(
                self::HOJA_TALONARIOS,
                $row,
                new Apunte(
                    null,
                    $fecha,
                    $cuenta,
                    $origen,
                    $iniciales !== '' ? $iniciales : null,
                    $concepto,
                    $obs === '' ? null : $obs,
                    new Dinero(number_format((float) $cant, 2, '.', '')),
                    false,
                    null,
                ),
            );
        }

        return $filas;
    }

    /**
     * @param list<FilaOrigenExcel> $filas
     */
    private function persistirApuntes(array $filas): int
    {
        foreach ($filas as $fila) {
            $this->apuntes->guardar($fila->apunte);
        }

        return count($filas);
    }

    private static function mesInt(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return ($n >= 1 && $n <= 12) ? $n : null;
    }
}
