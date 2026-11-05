<?php

declare(strict_types=1);

namespace src\remesas\infrastructure\persistence;

use PDO;
use src\remesas\domain\contracts\SaldoCcManualRepository;
use src\remesas\domain\entity\SaldoCcManual;

final class PdoSaldoCcManualRepository implements SaldoCcManualRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function guardar(SaldoCcManual $saldo): SaldoCcManual
    {
        $st = $this->pdo->prepare(
            'INSERT INTO saldos_cc_manuales (centro_id, ejercicio_id, persona_id, anio, mes, saldo_cents, nota)
             VALUES (:c, :e, :p, :a, :m, :s, :n)
             ON CONFLICT (persona_id, ejercicio_id, anio, mes) DO UPDATE SET
                saldo_cents = EXCLUDED.saldo_cents,
                nota = EXCLUDED.nota,
                updated_at = now()
             RETURNING id'
        );
        $st->execute([
            ':c' => $saldo->centroId,
            ':e' => $saldo->ejercicioId,
            ':p' => $saldo->personaId,
            ':a' => $saldo->anio,
            ':m' => $saldo->mes,
            ':s' => $saldo->saldoCents,
            ':n' => $saldo->nota,
        ]);
        $id = (int) $st->fetchColumn();

        return new SaldoCcManual(
            $id,
            $saldo->centroId,
            $saldo->ejercicioId,
            $saldo->personaId,
            $saldo->anio,
            $saldo->mes,
            $saldo->saldoCents,
            $saldo->nota,
        );
    }

    public function porId(int $id): ?SaldoCcManual
    {
        $st = $this->pdo->prepare('SELECT * FROM saldos_cc_manuales WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function borrar(int $id): void
    {
        $this->pdo->prepare('DELETE FROM saldos_cc_manuales WHERE id = :id')->execute([':id' => $id]);
    }

    public function listarDeEjercicio(int $centroId, int $ejercicioId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM saldos_cc_manuales
             WHERE centro_id = :c AND ejercicio_id = :e
             ORDER BY anio DESC, mes DESC, persona_id'
        );
        $st->execute([':c' => $centroId, ':e' => $ejercicioId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): SaldoCcManual
    {
        return new SaldoCcManual(
            (int) $row['id'],
            (int) $row['centro_id'],
            (int) $row['ejercicio_id'],
            (int) $row['persona_id'],
            (int) $row['anio'],
            (int) $row['mes'],
            (int) $row['saldo_cents'],
            $row['nota'] !== null ? (string) $row['nota'] : null,
        );
    }
}
