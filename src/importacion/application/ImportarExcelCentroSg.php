<?php

declare(strict_types=1);

namespace src\importacion\application;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\importacion\infrastructure\excel\XlsxReader;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;
use src\personas\infrastructure\persistence\PdoPersonaSg;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\infrastructure\excel\ExcelDate;

/**
 * Carga un libro «Secretario sg» (nombres, talonario y fecha de cierre)
 * en un centro de plan H16s. La contrapartida de cada apunte es la caja.
 */
final class ImportarExcelCentroSg
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly AsientoRepository $asientos,
        private readonly PersonaRepository $personas,
        private readonly PdoPersonaSg $fichas,
    ) {
    }

    /** @return array{personas:int,apuntes:int,fecha_cierre:string} */
    public function ejecutar(string $path, string $codigoCentro): array
    {
        $centro = $this->centro($codigoCentro);
        $ejercicioId = $this->ejercicioAbierto((int) $centro['id']);
        $book = new XlsxReader($path);
        $this->pdo->beginTransaction();
        try {
            $this->renombrarDestinos((int) $centro['id'], $book->sheet('Conceptos'));
            $personas = $this->importarNombres((int) $centro['id'], $book->sheet('Nombres'));
            $this->borrarImportacionPrevia($ejercicioId);
            $n = $this->importarTalonario($ejercicioId, (int) $centro['id'], $book->sheet('Talonarios'), $personas);
            $cierre = $this->fechaCierre($book->sheet('Conceptos'), $book->sheet('Talonarios'));
            $this->pdo->prepare('UPDATE ejercicios SET fecha_corte = :f WHERE id = :id')
                ->execute([':f' => $cierre, ':id' => $ejercicioId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'personas' => count($personas),
            'apuntes' => $n,
            'fecha_cierre' => $cierre,
        ];
    }

    /** @return array{id:int,codigo:string} */
    private function centro(string $codigo): array
    {
        $st = $this->pdo->prepare(
            'SELECT c.id, c.codigo, p.codigo AS plan
             FROM centros c
             LEFT JOIN planes_contables p ON p.id = c.plan_contable_id
             WHERE c.codigo = :c'
        );
        $st->execute([':c' => $codigo]);
        $row = $st->fetch();
        if (!is_array($row)) {
            throw new InvalidArgumentException('No existe el centro ' . $codigo);
        }
        if (!CatalogoPlanesContables::esCentroSg((string) ($row['plan'] ?? ''))) {
            throw new InvalidArgumentException('El centro no es de plan H16s');
        }

        return ['id' => (int) $row['id'], 'codigo' => (string) $row['codigo']];
    }

    private function ejercicioAbierto(int $centroId): int
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM ejercicios WHERE centro_id = :c AND estado = 'abierto' ORDER BY fecha_inicio DESC LIMIT 1"
        );
        $st->execute([':c' => $centroId]);
        $id = $st->fetchColumn();
        if ($id === false) {
            throw new InvalidArgumentException('El centro no tiene un ejercicio abierto');
        }

        return (int) $id;
    }

    /** @param array<int, array<string, mixed>> $conceptos */
    private function renombrarDestinos(int $centroId, array $conceptos): void
    {
        $st = $this->pdo->prepare(
            "UPDATE cuentas SET nombre = :n, descripcion = :n
             WHERE centro_id = :c AND libro = 'G' AND codigo = :k"
        );
        $ins = $this->pdo->prepare(
            'INSERT INTO centro_destinos_sg (centro_id, codigo, etiqueta, orden)
             VALUES (:c, :k, :n, :o)
             ON CONFLICT (centro_id, codigo) DO UPDATE SET etiqueta = excluded.etiqueta, orden = excluded.orden'
        );
        foreach ($conceptos as $row) {
            $texto = trim((string) ($row['M'] ?? ''));
            if (!preg_match('/^(\d+)\s+(.+)$/', $texto, $m)) {
                continue;
            }
            $codigo = $m[1];
            if ((int) $codigo < 42 || (int) $codigo > 54) {
                continue;
            }
            $nombre = trim($m[2]);
            $st->execute([':n' => $nombre, ':c' => $centroId, ':k' => $codigo]);
            $ins->execute([':c' => $centroId, ':k' => $codigo, ':n' => $nombre, ':o' => (int) $codigo]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $hoja
     * @return array<string, int> nombre normalizado => persona id
     */
    private function importarNombres(int $centroId, array $hoja): array
    {
        $usadas = [];
        $st = $this->pdo->prepare('SELECT iniciales FROM personas WHERE centro_id = :c');
        $st->execute([':c' => $centroId]);
        foreach ($st->fetchAll() as $row) {
            $usadas[strtolower((string) $row['iniciales'])] = true;
        }
        $porNombre = [];
        $orden = 0;
        foreach ($hoja as $fila => $row) {
            if ($fila < 2) {
                continue;
            }
            $bruto = trim((string) ($row['B'] ?? ''));
            if ($bruto === '' || str_contains(mb_strtolower($bruto), 'apellido')) {
                continue;
            }
            $orden++;
            [$apellidos, $nombre] = $this->partirNombre($bruto);
            $clave = $this->claveNombre($bruto);
            $existente = $this->buscarPorNombre($centroId, $apellidos, $nombre);
            if ($existente === null) {
                $iniciales = $this->inicialesDe($apellidos, $nombre, $usadas);
                $existente = $this->personas->guardar(new Persona(
                    null,
                    $nombre !== '' ? $nombre : $apellidos,
                    $nombre !== '' ? $apellidos : '',
                    $iniciales,
                    null,
                    null,
                    null,
                    null,
                    null,
                    $orden,
                    $centroId,
                ));
            }
            if ($existente->id === null) {
                throw new InvalidArgumentException('No se pudo guardar ' . $bruto);
            }
            $clase = strtolower(trim((string) ($row['D'] ?? ''))) === 'cp' ? 'cp' : 's';
            $grupo = (int) ($row['C'] ?? 1);
            $this->fichas->guardar($existente->id, $grupo > 0 ? $grupo : 1, $clase);
            $porNombre[$clave] = $existente->id;
        }

        return $porNombre;
    }

    private function buscarPorNombre(int $centroId, string $apellidos, string $nombre): ?Persona
    {
        foreach ($this->personas->listarDeCentro($centroId) as $p) {
            if (mb_strtolower($p->apellidos) === mb_strtolower($apellidos)
                && mb_strtolower($p->nombre) === mb_strtolower($nombre)) {
                return $p;
            }
        }

        return null;
    }

    /** @param array<string, bool> $usadas */
    private function inicialesDe(string $apellidos, string $nombre, array &$usadas): string
    {
        $base = $this->ascii($apellidos !== '' ? $apellidos : $nombre);
        $base = substr($base, 0, 4);
        if ($base === '') {
            $base = 'nom';
        }
        $candidato = $base;
        $n = 2;
        while (isset($usadas[$candidato])) {
            $candidato = substr($base, 0, 3) . $n;
            $n++;
        }
        $usadas[$candidato] = true;

        return $candidato;
    }

    private function ascii(string $texto): string
    {
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $t = strtolower(is_string($t) ? $t : $texto);
        $t = preg_replace('/[^a-z0-9]/', '', $t) ?? '';

        return $t;
    }

    /** @return array{0:string,1:string} */
    private function partirNombre(string $bruto): array
    {
        $partes = explode(',', $bruto, 2);
        $apellidos = trim($partes[0]);
        $nombre = isset($partes[1]) ? trim($partes[1]) : '';

        return [$apellidos, $nombre];
    }

    private function claveNombre(string $bruto): string
    {
        $t = mb_strtolower(trim($bruto));
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;
        $t = str_replace(' ,', ',', $t);

        return $t;
    }

    private function borrarImportacionPrevia(int $ejercicioId): void
    {
        $this->pdo->prepare(
            "DELETE FROM movimientos WHERE asiento_id IN (
                SELECT id FROM asientos WHERE ejercicio_id = :e AND origen = 'import'
             )"
        )->execute([':e' => $ejercicioId]);
        $this->pdo->prepare(
            "DELETE FROM asientos WHERE ejercicio_id = :e AND origen = 'import'"
        )->execute([':e' => $ejercicioId]);
    }

    /**
     * @param array<int, array<string, mixed>> $hoja
     * @param array<string, int> $personas
     */
    private function importarTalonario(int $ejercicioId, int $centroId, array $hoja, array $personas): int
    {
        $cajaId = $this->cuentaId($centroId, 'CAJA');
        $cuentas = $this->cuentasGastoIngreso($centroId);
        $n = 0;
        foreach ($hoja as $fila => $row) {
            if ($fila < 2) {
                continue;
            }
            $fecha = ExcelDate::parseCell($row['A'] ?? null);
            $codigo = trim((string) ($row['C'] ?? ''));
            $importe = $this->cents($row['E'] ?? null);
            if ($fecha === null || $codigo === '' || $importe === 0) {
                continue;
            }
            if (!isset($cuentas[$codigo])) {
                throw new InvalidArgumentException('Concepto sin cuenta en la fila ' . $fila . ': ' . $codigo);
            }
            $nombre = trim((string) ($row['B'] ?? ''));
            $personaId = $nombre === '' ? null : ($personas[$this->claveNombre($nombre)] ?? null);
            if ($nombre !== '' && $personaId === null) {
                throw new InvalidArgumentException('Nombre no encontrado en la fila ' . $fila . ': ' . $nombre);
            }
            $obs = trim((string) ($row['D'] ?? ''));
            $conceptoId = $cuentas[$codigo];
            $entraCaja = $this->entraEnCaja($codigo);
            $movimientos = $entraCaja
                ? [
                    new Movimiento(null, 1, $cajaId, null, $importe, 0),
                    new Movimiento(null, 2, $conceptoId, $personaId, 0, $importe),
                ]
                : [
                    new Movimiento(null, 1, $conceptoId, $personaId, $importe, 0),
                    new Movimiento(null, 2, $cajaId, null, 0, $importe),
                ];
            $this->asientos->guardar(new Asiento(
                null,
                $ejercicioId,
                'G',
                null,
                $fecha,
                $obs !== '' ? $obs : null,
                $codigo === '32' ? 'apertura' : 'normal',
                'import',
                $personaId,
                $movimientos,
                $codigo,
                null,
                $fecha,
            ));
            $n++;
        }

        return $n;
    }

    private function entraEnCaja(string $codigo): bool
    {
        $n = (int) $codigo;

        return ($n >= 11 && $n <= 14) || $codigo === '32';
    }

    private function cuentaId(int $centroId, string $codigo): int
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND imputable = TRUE
               AND (codigo = :k OR codigo_maestro = :k)
             ORDER BY codigo
             LIMIT 1"
        );
        $st->execute([':c' => $centroId, ':k' => $codigo]);
        $id = $st->fetchColumn();
        if ($id === false) {
            throw new InvalidArgumentException('Falta la cuenta ' . $codigo);
        }

        return (int) $id;
    }

    /** @return array<string, int> */
    private function cuentasGastoIngreso(int $centroId): array
    {
        $st = $this->pdo->prepare(
            "SELECT codigo, id FROM cuentas
             WHERE centro_id = :c AND libro = 'G' AND persona_id IS NULL AND imputable = TRUE"
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(string) $row['codigo']] = (int) $row['id'];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $conceptos
     * @param array<int, array<string, mixed>> $talonario
     */
    private function fechaCierre(array $conceptos, array $talonario): string
    {
        $deHoja = ExcelDate::parseCell($conceptos[3]['H'] ?? null);
        $max = null;
        foreach ($talonario as $fila => $row) {
            if ($fila < 2) {
                continue;
            }
            $f = ExcelDate::parseCell($row['A'] ?? null);
            if ($f !== null && ($max === null || $f > $max)) {
                $max = $f;
            }
        }
        $fecha = $deHoja ?? $max ?? new DateTimeImmutable('today');

        return $fecha->format('Y-m-d');
    }

    private function cents(mixed $valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }
        $n = (float) str_replace(',', '.', (string) $valor);

        return (int) round($n * 100);
    }
}
