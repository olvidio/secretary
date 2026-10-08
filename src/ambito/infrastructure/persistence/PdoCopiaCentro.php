<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\persistence;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\shared\infrastructure\persistence\ConverterDate;

/**
 * Volcado y restauración de un solo centro. No toca los demás.
 * El listado de nombres no viaja en la copia: tienen que existir ya en el centro.
 * Cada asiento sí lleva las iniciales de su persona, para no perder el vínculo al restaurar.
 */
final class PdoCopiaCentro
{
    private const VERSION = 1;

    public function __construct(
        private readonly PDO $pdo,
        private readonly AsientoRepository $asientos,
    ) {
    }

    /** @return array<string, mixed> */
    public function exportar(int $centroId): array
    {
        $centro = $this->filaCentro($centroId);
        $st = $this->pdo->prepare(
            'SELECT a.*, p.iniciales AS persona_iniciales
             FROM asientos a
             JOIN ejercicios e ON e.id = a.ejercicio_id
             LEFT JOIN personas p ON p.id = a.persona_id
             WHERE e.centro_id = :c AND a.anulado_at IS NULL
             ORDER BY a.fecha, a.libro, a.numero, a.id'
        );
        $st->execute([':c' => $centroId]);
        $asientos = [];
        foreach ($st->fetchAll() as $row) {
            $fecha = (new ConverterDate('date', $row['fecha']))->fromPg();
            $fechaOp = (new ConverterDate('date', $row['fecha_operacion'] ?? $row['fecha']))->fromPg();
            if ($fecha === null || $fechaOp === null) {
                continue;
            }
            $lineas = [];
            $stM = $this->pdo->prepare(
                'SELECT m.orden, m.debe, m.haber, c.codigo, c.libro, p.iniciales
                 FROM movimientos m
                 JOIN cuentas c ON c.id = m.cuenta_id
                 LEFT JOIN personas p ON p.id = c.persona_id
                 WHERE m.asiento_id = :a
                 ORDER BY m.orden'
            );
            $stM->execute([':a' => (int) $row['id']]);
            foreach ($stM->fetchAll() as $mov) {
                $lineas[] = [
                    'orden' => (int) $mov['orden'],
                    'codigo' => (string) $mov['codigo'],
                    'libro' => (string) $mov['libro'],
                    'iniciales' => $mov['iniciales'] !== null ? (string) $mov['iniciales'] : '',
                    'debe_cents' => (int) $mov['debe'],
                    'haber_cents' => (int) $mov['haber'],
                ];
            }
            $asientos[] = [
                'ref' => (int) $row['id'],
                'par_ref' => isset($row['asiento_par_id']) && $row['asiento_par_id'] !== null
                    ? (int) $row['asiento_par_id'] : null,
                'libro' => (string) $row['libro'],
                'fecha' => $fecha->format('Y-m-d'),
                'fecha_operacion' => $fechaOp->format('Y-m-d'),
                'glosa' => $row['glosa'] !== null ? (string) $row['glosa'] : null,
                'tipo' => (string) $row['tipo'],
                'origen' => (string) $row['origen'],
                'iniciales' => $row['persona_iniciales'] !== null
                    ? strtolower((string) $row['persona_iniciales']) : '',
                'gasto_generales' => self::booleano($row['gasto_generales'] ?? false),
                'concepto_generales' => isset($row['concepto_generales']) && $row['concepto_generales'] !== ''
                    ? (string) $row['concepto_generales'] : null,
                'lineas' => $lineas,
            ];
        }

        $listados = [];
        $stL = $this->pdo->prepare(
            'SELECT nombre, mostrar_movimientos, mostrar_totales, fecha_desde, fecha_hasta, categorias, terceros
             FROM listados WHERE centro_id = :c ORDER BY nombre'
        );
        $stL->execute([':c' => $centroId]);
        foreach ($stL->fetchAll() as $row) {
            $listados[] = [
                'nombre' => (string) $row['nombre'],
                'mostrar_movimientos' => (bool) $row['mostrar_movimientos'],
                'mostrar_totales' => (bool) $row['mostrar_totales'],
                'fecha_desde' => $row['fecha_desde'] !== null ? (string) $row['fecha_desde'] : null,
                'fecha_hasta' => $row['fecha_hasta'] !== null ? (string) $row['fecha_hasta'] : null,
                'categorias' => $this->json($row['categorias']),
                'terceros' => $this->json($row['terceros']),
            ];
        }

        return [
            'version' => self::VERSION,
            'ambito' => 'centro',
            'exportado' => (new DateTimeImmutable())->format('c'),
            'centro_id' => $centroId,
            'codigo' => (string) $centro['codigo'],
            'nombre' => (string) $centro['nombre'],
            'asientos' => $asientos,
            'listados' => $listados,
        ];
    }

    /** @param array<string, mixed> $datos */
    public function restaurar(int $centroId, array $datos): int
    {
        if ((int) ($datos['version'] ?? 0) !== self::VERSION || ($datos['ambito'] ?? '') !== 'centro') {
            throw new InvalidArgumentException(_('La copia no es de un centro de este programa'));
        }
        if ((int) ($datos['centro_id'] ?? 0) !== $centroId) {
            throw new InvalidArgumentException(_('Esta copia es de otro centro y no se puede restaurar aquí'));
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'DELETE FROM asientos WHERE ejercicio_id IN (SELECT id FROM ejercicios WHERE centro_id = :c)'
            )->execute([':c' => $centroId]);
            $this->pdo->prepare('DELETE FROM listados WHERE centro_id = :c')->execute([':c' => $centroId]);
            $n = 0;
            $mapa = [];
            $pendientesPar = [];
            foreach ($datos['asientos'] ?? [] as $fila) {
                if (!is_array($fila)) {
                    continue;
                }
                $guardado = $this->asientos->guardar($this->asientoDe($centroId, $fila), true);
                $n++;
                $ref = (int) ($fila['ref'] ?? 0);
                if ($ref > 0 && $guardado->id !== null) {
                    $mapa[$ref] = $guardado->id;
                }
                $parRef = isset($fila['par_ref']) && $fila['par_ref'] !== null ? (int) $fila['par_ref'] : 0;
                if ($parRef > 0 && $guardado->id !== null) {
                    $pendientesPar[] = ['id' => $guardado->id, 'par_ref' => $parRef];
                }
            }
            foreach ($pendientesPar as $item) {
                $parNuevo = $mapa[$item['par_ref']] ?? null;
                if ($parNuevo !== null) {
                    $this->asientos->enlazar($item['id'], $parNuevo);
                }
            }
            $this->restaurarListados($centroId, $datos['listados'] ?? []);
            $this->pdo->commit();

            return $n;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $fila */
    private function asientoDe(int $centroId, array $fila): Asiento
    {
        $fecha = (string) ($fila['fecha'] ?? '');
        $ejercicioId = $this->ejercicioDe($centroId, $fecha);
        if ($ejercicioId === null) {
            throw new InvalidArgumentException(sprintf(_('No hay ejercicio para la fecha %s'), $fecha));
        }
        $movimientos = [];
        $inicialesLineas = [];
        foreach ($fila['lineas'] ?? [] as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            $inicialesLinea = strtolower(trim((string) ($linea['iniciales'] ?? '')));
            if ($inicialesLinea !== '') {
                $inicialesLineas[$inicialesLinea] = true;
            }
            $cuentaId = $this->cuentaId(
                $centroId,
                (string) ($linea['libro'] ?? $fila['libro'] ?? 'G'),
                (string) ($linea['codigo'] ?? ''),
                $inicialesLinea,
            );
            $movimientos[] = new Movimiento(
                null,
                (int) ($linea['orden'] ?? (count($movimientos) + 1)),
                $cuentaId,
                $inicialesLinea !== '' ? $this->personaIdDe($centroId, $inicialesLinea) : null,
                (int) ($linea['debe_cents'] ?? 0),
                (int) ($linea['haber_cents'] ?? 0),
            );
        }

        $iniciales = strtolower(trim((string) ($fila['iniciales'] ?? '')));
        if ($iniciales === '' && count($inicialesLineas) === 1) {
            $iniciales = (string) array_key_first($inicialesLineas);
        }
        $personaId = $iniciales !== '' ? $this->personaIdDe($centroId, $iniciales) : null;
        if ($iniciales !== '' && $personaId === null) {
            throw new InvalidArgumentException(sprintf(
                _('No está la persona «%s» en este centro. La copia no trae el listado de nombres: tienen que existir antes de restaurar.'),
                $iniciales
            ));
        }

        return new Asiento(
            null,
            $ejercicioId,
            (string) ($fila['libro'] ?? 'G'),
            null,
            new DateTimeImmutable($fecha),
            isset($fila['glosa']) ? (string) $fila['glosa'] : null,
            (string) ($fila['tipo'] ?? 'normal'),
            (string) ($fila['origen'] ?? 'import'),
            $personaId,
            $movimientos,
            null,
            null,
            new DateTimeImmutable((string) ($fila['fecha_operacion'] ?? $fecha)),
            null,
            self::booleano($fila['gasto_generales'] ?? false),
            isset($fila['concepto_generales']) && $fila['concepto_generales'] !== ''
                ? (string) $fila['concepto_generales'] : null,
        );
    }

    private function personaIdDe(int $centroId, string $iniciales): ?int
    {
        $st = $this->pdo->prepare(
            'SELECT id FROM personas
             WHERE centro_id = :c AND lower(iniciales) = lower(:i)
             ORDER BY activo DESC, id
             LIMIT 1'
        );
        $st->execute([':c' => $centroId, ':i' => $iniciales]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private static function booleano(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_int($valor) || is_float($valor)) {
            return (int) $valor === 1;
        }
        if (is_string($valor)) {
            return in_array(strtolower($valor), ['1', 't', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function cuentaId(int $centroId, string $libro, string $codigo, string $iniciales): int
    {
        $sql = 'SELECT c.id FROM cuentas c LEFT JOIN personas p ON p.id = c.persona_id
                WHERE c.centro_id = :c AND c.libro = :lib AND c.codigo = :cod AND c.activo = TRUE AND ';
        $sql .= $iniciales === ''
            ? 'c.persona_id IS NULL'
            : 'lower(p.iniciales) = lower(:ini)';
        $sql .= ' LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $params = [':c' => $centroId, ':lib' => $libro, ':cod' => $codigo];
        if ($iniciales !== '') {
            $params[':ini'] = $iniciales;
        }
        $st->execute($params);
        $id = $st->fetchColumn();
        if ($id === false) {
            throw new InvalidArgumentException(sprintf(_('Falta la cuenta %s del libro %s'), $codigo, $libro));
        }

        return (int) $id;
    }

    private function ejercicioDe(int $centroId, string $fecha): ?int
    {
        $st = $this->pdo->prepare(
            'SELECT id FROM ejercicios
             WHERE centro_id = :c AND fecha_inicio <= :f AND fecha_fin >= :f
             ORDER BY fecha_inicio DESC LIMIT 1'
        );
        $st->execute([':c' => $centroId, ':f' => $fecha]);
        $id = $st->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** @param list<mixed> $listados */
    private function restaurarListados(int $centroId, array $listados): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO listados (centro_id, nombre, mostrar_movimientos, mostrar_totales, fecha_desde, fecha_hasta, categorias, terceros)
             VALUES (:c, :n, :m, :t, :d, :h, :cat, :ter)
             ON CONFLICT (centro_id, nombre) DO NOTHING'
        );
        foreach ($listados as $fila) {
            if (!is_array($fila) || trim((string) ($fila['nombre'] ?? '')) === '') {
                continue;
            }
            $st->execute([
                ':c' => $centroId,
                ':n' => (string) $fila['nombre'],
                ':m' => !empty($fila['mostrar_movimientos']) ? 1 : 0,
                ':t' => !empty($fila['mostrar_totales']) ? 1 : 0,
                ':d' => $fila['fecha_desde'] ?? null,
                ':h' => $fila['fecha_hasta'] ?? null,
                ':cat' => json_encode($fila['categorias'] ?? [], JSON_UNESCAPED_UNICODE),
                ':ter' => json_encode($fila['terceros'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function filaCentro(int $centroId): array
    {
        $st = $this->pdo->prepare('SELECT codigo, nombre FROM centros WHERE id = :id');
        $st->execute([':id' => $centroId]);
        $row = $st->fetch();
        if (!is_array($row)) {
            throw new InvalidArgumentException(_('Centro no encontrado'));
        }

        return $row;
    }

    /** @return list<mixed> */
    private function json(mixed $valor): array
    {
        if (is_string($valor)) {
            $decoded = json_decode($valor, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($valor) ? $valor : [];
    }
}
