<?php

declare(strict_types=1);

namespace src\disponible\infrastructure\persistence;

use PDO;
use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\shared\domain\value_objects\Dinero;

final class PdoAsignacionLaboresRepository implements AsignacionLaboresRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function guardarBorrador(int $centroId, int $ejercicioId, array $lineas): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->borrarBorradores($centroId, $ejercicioId);
            $st = $this->pdo->prepare(
                "INSERT INTO asignaciones_labores (centro_id, ejercicio_id, estado)
                 VALUES (:c, :e, 'borrador') RETURNING id"
            );
            $st->execute([':c' => $centroId, ':e' => $ejercicioId]);
            $id = (int) $st->fetchColumn();
            $ins = $this->pdo->prepare(
                'INSERT INTO asignaciones_labores_lineas
                    (asignacion_id, persona_id, codigo_maestro, importe_cents, pendiente_cents)
                 VALUES (:a, :p, :cod, :imp, :imp)'
            );
            foreach ($lineas as $l) {
                $ins->execute([
                    ':a' => $id,
                    ':p' => $l['persona_id'],
                    ':cod' => $l['codigo_maestro'],
                    ':imp' => $l['importe_cents'],
                ]);
            }
            $this->pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function borrarBorradores(int $centroId, int $ejercicioId): void
    {
        $this->pdo->prepare(
            "DELETE FROM asignaciones_labores
              WHERE centro_id = :c AND ejercicio_id = :e AND estado = 'borrador'"
        )->execute([':c' => $centroId, ':e' => $ejercicioId]);
    }

    public function marcarConfirmada(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE asignaciones_labores SET estado = 'confirmada', confirmada_at = now() WHERE id = :id"
        )->execute([':id' => $id]);
    }

    public function listarDeCentro(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM asignaciones_labores WHERE centro_id = :c ORDER BY id DESC LIMIT 20'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function porId(int $id, int $centroId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM asignaciones_labores WHERE id = :id AND centro_id = :c'
        );
        $st->execute([':id' => $id, ':c' => $centroId]);
        $row = $st->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function pendientesDePersona(int $centroId, int $personaId): array
    {
        return $this->pendientes($centroId, $personaId, null);
    }

    public function pendientesConfirmadosDePersona(int $centroId, int $personaId): array
    {
        return $this->pendientes($centroId, $personaId, 'confirmada');
    }

    public function registrarConsumos(int $remesaId, array $consumos): void
    {
        if ($consumos === []) {
            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO asignacion_labores_consumos (linea_id, remesa_id, importe_cents)
             VALUES (:l, :r, :imp)'
        );
        $upd = $this->pdo->prepare(
            'UPDATE asignaciones_labores_lineas
             SET pendiente_cents = pendiente_cents - :imp
             WHERE id = :id AND pendiente_cents >= :imp'
        );
        foreach ($consumos as $c) {
            $ins->execute([
                ':l' => $c['linea_id'],
                ':r' => $remesaId,
                ':imp' => $c['importe_cents'],
            ]);
            $upd->execute([':imp' => $c['importe_cents'], ':id' => $c['linea_id']]);
        }
    }

    public function revertirConsumosDeRemesa(int $remesaId): void
    {
        $st = $this->pdo->prepare(
            'SELECT linea_id, importe_cents FROM asignacion_labores_consumos WHERE remesa_id = :r'
        );
        $st->execute([':r' => $remesaId]);
        $upd = $this->pdo->prepare(
            'UPDATE asignaciones_labores_lineas SET pendiente_cents = pendiente_cents + :imp WHERE id = :id'
        );
        foreach ($st->fetchAll() as $row) {
            $upd->execute([':imp' => (int) $row['importe_cents'], ':id' => (int) $row['linea_id']]);
        }
        $this->pdo->prepare('DELETE FROM asignacion_labores_consumos WHERE remesa_id = :r')
            ->execute([':r' => $remesaId]);
    }

    public function instruccionesPendientesDePersona(int $centroId, int $personaId): array
    {
        $st = $this->pdo->prepare(
            "SELECT l.codigo_maestro, l.pendiente_cents
             FROM asignaciones_labores_lineas l
             INNER JOIN asignaciones_labores a ON a.id = l.asignacion_id
             WHERE a.centro_id = :c AND a.estado = 'confirmada'
               AND l.persona_id = :p AND l.pendiente_cents > 0
             ORDER BY a.id, l.codigo_maestro"
        );
        $st->execute([':c' => $centroId, ':p' => $personaId]);
        $porCodigo = [];
        foreach ($st->fetchAll() as $row) {
            $cod = (string) $row['codigo_maestro'];
            $porCodigo[$cod] = ($porCodigo[$cod] ?? 0) + (int) $row['pendiente_cents'];
        }
        $out = [];
        foreach ($porCodigo as $cod => $cents) {
            $out[] = [
                'codigo_maestro' => $cod,
                'importe_cents' => $cents,
                'importe_es' => Dinero::fromCents($cents)->formatEs(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id:int, codigo_maestro:string, pendiente_cents:int, importe_cents:int, asignacion_id:int}>
     */
    private function pendientes(int $centroId, int $personaId, ?string $estado): array
    {
        $sql = 'SELECT l.id, l.codigo_maestro, l.pendiente_cents, l.importe_cents, l.asignacion_id
                FROM asignaciones_labores_lineas l
                INNER JOIN asignaciones_labores a ON a.id = l.asignacion_id
                WHERE a.centro_id = :c AND l.persona_id = :p AND l.pendiente_cents > 0';
        $params = [':c' => $centroId, ':p' => $personaId];
        if ($estado !== null) {
            $sql .= ' AND a.estado = :e';
            $params[':e'] = $estado;
        }
        $sql .= ' ORDER BY a.id, l.codigo_maestro';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'codigo_maestro' => (string) $row['codigo_maestro'],
                'pendiente_cents' => (int) $row['pendiente_cents'],
                'importe_cents' => (int) $row['importe_cents'],
                'asignacion_id' => (int) $row['asignacion_id'],
            ];
        }

        return $out;
    }

    public function realizadoLaboresPorPersona(int $ejercicioId): array
    {
        $st = $this->pdo->prepare(
            "SELECT m.persona_id, c.codigo_maestro, COALESCE(SUM(m.debe - m.haber), 0) AS cents
             FROM movimientos m
             INNER JOIN asientos a ON a.id = m.asiento_id
             INNER JOIN cuentas c ON c.id = m.cuenta_id
             WHERE a.ejercicio_id = :ej AND a.libro = 'P' AND a.anulado_at IS NULL
               AND m.persona_id IS NOT NULL
               AND c.codigo_maestro ~ '^7[0-9]{1,2}$'
             GROUP BY m.persona_id, c.codigo_maestro"
        );
        $st->execute([':ej' => $ejercicioId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'persona_id' => (int) $row['persona_id'],
                'codigo_maestro' => (string) $row['codigo_maestro'],
                'cents' => (int) $row['cents'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $id = (int) $row['id'];
        $st = $this->pdo->prepare(
            'SELECT * FROM asignaciones_labores_lineas WHERE asignacion_id = :id ORDER BY persona_id, codigo_maestro'
        );
        $st->execute([':id' => $id]);
        $lineas = [];
        foreach ($st->fetchAll() as $l) {
            $cents = (int) $l['importe_cents'];
            $lineas[] = [
                'id' => (int) $l['id'],
                'persona_id' => (int) $l['persona_id'],
                'codigo_maestro' => (string) $l['codigo_maestro'],
                'importe_cents' => $cents,
                'importe_es' => Dinero::fromCents($cents)->formatEs(),
                'pendiente_cents' => (int) $l['pendiente_cents'],
                'pendiente_es' => Dinero::fromCents((int) $l['pendiente_cents'])->formatEs(),
            ];
        }

        return [
            'id' => $id,
            'centro_id' => (int) $row['centro_id'],
            'ejercicio_id' => (int) $row['ejercicio_id'],
            'estado' => (string) $row['estado'],
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'confirmada_at' => isset($row['confirmada_at']) ? (string) $row['confirmada_at'] : null,
            'lineas' => $lineas,
        ];
    }
}
