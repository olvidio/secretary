<?php

declare(strict_types=1);

namespace src\apuntes\infrastructure\persistence;

use PDO;
use src\apuntes\application\AsegurarPendienteBancoCentro;
use src\apuntes\domain\contracts\BancoCentroImportRepository;
use src\shared\infrastructure\persistence\ConverterDate;

final class PdoBancoCentroImportRepository implements BancoCentroImportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function existe(int $centroId, ?int $cuentaFisicaId, string $banco, string $huella): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM banco_centro_import_filas
             WHERE centro_id = :c AND COALESCE(cuenta_fisica_id, 0) = :f AND banco = :b AND huella = :h'
        );
        $st->execute([
            ':c' => $centroId,
            ':f' => $cuentaFisicaId ?? 0,
            ':b' => $banco,
            ':h' => $huella,
        ]);

        return $st->fetchColumn() !== false;
    }

    public function guardar(
        int $centroId,
        ?int $cuentaFisicaId,
        string $banco,
        string $huella,
        string $fecha,
        string $importe,
        string $concepto,
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO banco_centro_import_filas
                (centro_id, cuenta_fisica_id, banco, huella, fecha, importe, concepto)
             VALUES (:c, :f, :b, :h, :fe, :i, :co)'
        );
        $st->execute([
            ':c' => $centroId,
            ':f' => $cuentaFisicaId,
            ':b' => $banco,
            ':h' => $huella,
            ':fe' => (new ConverterDate('date', $fecha))->toPg(),
            ':i' => $importe,
            ':co' => $concepto,
        ]);
    }

    public function porId(int $centroId, int $filaId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT id, asiento_id, fecha, importe, concepto, banco, cuenta_fisica_id, persona_id, concepto_asignado
             FROM banco_centro_import_filas
             WHERE id = :id AND centro_id = :c'
        );
        $st->execute([':id' => $filaId, ':c' => $centroId]);
        $row = $st->fetch();
        if ($row === false) {
            return null;
        }

        return $this->hydrateFila($row);
    }

    public function vincularAsiento(
        int $filaId,
        int $asientoId,
        ?int $personaId,
        string $conceptoAsignado,
    ): void {
        $st = $this->pdo->prepare(
            'UPDATE banco_centro_import_filas
             SET asiento_id = :a, persona_id = :p, concepto_asignado = :co
             WHERE id = :id'
        );
        $st->execute([
            ':id' => $filaId,
            ':a' => $asientoId,
            ':p' => $personaId,
            ':co' => $conceptoAsignado,
        ]);
    }

    public function deCuentas(int $centroId, array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }
        $ph = [];
        $params = [':c' => $centroId];
        foreach (array_values($codigos) as $i => $codigo) {
            $k = ':co' . $i;
            $ph[] = $k;
            $params[$k] = $codigo;
        }
        $st = $this->pdo->prepare(
            'SELECT f.id AS fila_id, f.asiento_id, f.fecha, f.importe, f.concepto, f.banco, f.cuenta_fisica_id,
                    a.glosa AS nota, c.codigo AS categoria_codigo
             FROM banco_centro_import_filas f
             JOIN asientos a ON a.id = f.asiento_id AND a.anulado_at IS NULL
             JOIN movimientos m ON m.asiento_id = a.id
             JOIN cuentas c ON c.id = m.cuenta_id
             WHERE f.centro_id = :c
               AND f.persona_id IS NULL
               AND f.asiento_id IS NOT NULL
               AND c.codigo IN (' . implode(', ', $ph) . ')
             ORDER BY f.fecha DESC, f.id DESC'
        );
        $st->execute($params);

        return $this->mapFilas($st->fetchAll());
    }

    public function sinAsentar(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT id AS fila_id, asiento_id, fecha, importe, concepto, banco, cuenta_fisica_id, persona_id, concepto_asignado
             FROM banco_centro_import_filas
             WHERE centro_id = :c AND asiento_id IS NULL AND persona_id IS NULL
             ORDER BY fecha DESC, id DESC'
        );
        $st->execute([':c' => $centroId]);

        return $this->mapFilas($st->fetchAll(), incluirNota: false);
    }

    public function historialCategorizado(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT f.concepto, f.concepto_asignado, pc.naturaleza AS tipo
             FROM banco_centro_import_filas f
             JOIN centros ce ON ce.id = f.centro_id
             JOIN plan_conceptos pc ON pc.plan_contable_id = ce.plan_contable_id
                AND pc.cuenta = \'G\' AND pc.codigo = f.concepto_asignado
             WHERE f.centro_id = :c
               AND f.concepto_asignado IS NOT NULL
               AND f.concepto_asignado NOT IN (:g, :i, :og, :oi)
             ORDER BY f.fecha DESC, f.id DESC'
        );
        $st->execute([
            ':c' => $centroId,
            ':g' => AsegurarPendienteBancoCentro::CODIGO_PENDIENTE_GASTO,
            ':i' => AsegurarPendienteBancoCentro::CODIGO_PENDIENTE_INGRESO,
            ':og' => AsegurarPendienteBancoCentro::CODIGO_OTRA_GASTO,
            ':oi' => AsegurarPendienteBancoCentro::CODIGO_OTRA_INGRESO,
        ]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $tipo = (string) ($row['tipo'] ?? '');
            if (!in_array($tipo, ['ingreso', 'gasto'], true)) {
                continue;
            }
            $out[] = [
                'concepto' => (string) $row['concepto'],
                'concepto_codigo' => (string) $row['concepto_asignado'],
                'tipo' => $tipo,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateFila(array $row): array
    {
        $importe = (string) $row['importe'];
        $fecha = (new ConverterDate('date', $row['fecha']))->fromPg();

        return [
            'fila_id' => (int) $row['id'],
            'asiento_id' => isset($row['asiento_id']) && $row['asiento_id'] !== null ? (int) $row['asiento_id'] : null,
            'fecha' => $fecha !== null ? $fecha->format('Y-m-d') : '',
            'importe' => $importe,
            'concepto' => (string) $row['concepto'],
            'banco' => (string) $row['banco'],
            'cuenta_fisica_id' => isset($row['cuenta_fisica_id']) && $row['cuenta_fisica_id'] !== null
                ? (int) $row['cuenta_fisica_id'] : null,
            'persona_id' => isset($row['persona_id']) && $row['persona_id'] !== null
                ? (int) $row['persona_id'] : null,
            'concepto_asignado' => is_string($row['concepto_asignado'] ?? null)
                ? (string) $row['concepto_asignado'] : null,
            'sentido' => str_starts_with($importe, '-') ? 'gasto' : 'ingreso',
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function mapFilas(array $rows, bool $incluirNota = true): array
    {
        $out = [];
        foreach ($rows as $row) {
            $importe = (string) $row['importe'];
            $fecha = (new ConverterDate('date', $row['fecha']))->fromPg();
            $out[] = [
                'fila_id' => (int) $row['fila_id'],
                'asiento_id' => isset($row['asiento_id']) && $row['asiento_id'] !== null
                    ? (int) $row['asiento_id'] : null,
                'fecha' => $fecha !== null ? $fecha->format('Y-m-d') : '',
                'importe' => $importe,
                'concepto' => (string) $row['concepto'],
                'nota' => $incluirNota && isset($row['nota']) && is_string($row['nota']) ? (string) $row['nota'] : '',
                'banco' => (string) $row['banco'],
                'cuenta_fisica_id' => isset($row['cuenta_fisica_id']) && $row['cuenta_fisica_id'] !== null
                    ? (int) $row['cuenta_fisica_id'] : null,
                'categoria_codigo' => is_string($row['categoria_codigo'] ?? null)
                    ? (string) $row['categoria_codigo'] : null,
                'sentido' => str_starts_with($importe, '-') ? 'gasto' : 'ingreso',
            ];
        }

        return $out;
    }
}
