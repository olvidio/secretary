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

/** Volcado y restauración de un solo centro. No toca los demás. */
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
            'SELECT a.* FROM asientos a
             JOIN ejercicios e ON e.id = a.ejercicio_id
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
                'libro' => (string) $row['libro'],
                'fecha' => $fecha->format('Y-m-d'),
                'fecha_operacion' => $fechaOp->format('Y-m-d'),
                'glosa' => $row['glosa'] !== null ? (string) $row['glosa'] : null,
                'tipo' => (string) $row['tipo'],
                'origen' => (string) $row['origen'],
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
            foreach ($datos['asientos'] ?? [] as $fila) {
                if (!is_array($fila)) {
                    continue;
                }
                $this->asientos->guardar($this->asientoDe($centroId, $fila), true);
                $n++;
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
        foreach ($fila['lineas'] ?? [] as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            $cuentaId = $this->cuentaId(
                $centroId,
                (string) ($linea['libro'] ?? $fila['libro'] ?? 'G'),
                (string) ($linea['codigo'] ?? ''),
                (string) ($linea['iniciales'] ?? ''),
            );
            $movimientos[] = new Movimiento(
                null,
                (int) ($linea['orden'] ?? (count($movimientos) + 1)),
                $cuentaId,
                null,
                (int) ($linea['debe_cents'] ?? 0),
                (int) ($linea['haber_cents'] ?? 0),
            );
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
            null,
            $movimientos,
            null,
            null,
            new DateTimeImmutable((string) ($fila['fecha_operacion'] ?? $fecha)),
        );
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
