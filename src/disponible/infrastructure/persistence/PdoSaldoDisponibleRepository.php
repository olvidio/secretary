<?php

declare(strict_types=1);

namespace src\disponible\infrastructure\persistence;

use PDO;
use src\disponible\domain\contracts\SaldoDisponibleRepository;

final class PdoSaldoDisponibleRepository implements SaldoDisponibleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function saldoDe(int $centroId, int $personaId): int
    {
        $st = $this->pdo->prepare(
            'SELECT saldo_cents FROM saldos_disponibles WHERE centro_id = :c AND persona_id = :p'
        );
        $st->execute([':c' => $centroId, ':p' => $personaId]);
        $v = $st->fetchColumn();

        return $v === false ? 0 : (int) $v;
    }

    public function listarDeCentro(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT persona_id, saldo_cents FROM saldos_disponibles WHERE centro_id = :c'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[] = [
                'persona_id' => (int) $row['persona_id'],
                'saldo_cents' => (int) $row['saldo_cents'],
            ];
        }

        return $out;
    }

    public function aplicar(
        int $centroId,
        int $personaId,
        int $importeCents,
        string $fecha,
        string $origen,
        ?int $ejercicioId = null,
        ?int $remesaId = null,
        ?int $asignacionId = null,
        ?string $nota = null,
    ): int {
        $this->pdo->prepare(
            'INSERT INTO saldos_disponibles (centro_id, persona_id, saldo_cents, updated_at)
             VALUES (:c, :p, 0, now())
             ON CONFLICT (centro_id, persona_id) DO NOTHING'
        )->execute([':c' => $centroId, ':p' => $personaId]);

        $st = $this->pdo->prepare(
            'UPDATE saldos_disponibles SET saldo_cents = saldo_cents + :imp, updated_at = now()
             WHERE centro_id = :c AND persona_id = :p
             RETURNING saldo_cents'
        );
        $st->execute([':imp' => $importeCents, ':c' => $centroId, ':p' => $personaId]);
        $nuevo = (int) $st->fetchColumn();

        $ins = $this->pdo->prepare(
            'INSERT INTO saldos_disponibles_mov
                (centro_id, persona_id, ejercicio_id, fecha, origen, importe_cents, remesa_id, asignacion_id, nota)
             VALUES (:c, :p, :ej, :f, :o, :imp, :r, :a, :n)'
        );
        $ins->execute([
            ':c' => $centroId,
            ':p' => $personaId,
            ':ej' => $ejercicioId,
            ':f' => $fecha,
            ':o' => $origen,
            ':imp' => $importeCents,
            ':r' => $remesaId,
            ':a' => $asignacionId,
            ':n' => $nota,
        ]);

        return $nuevo;
    }

    public function revertirPorRemesa(int $remesaId): void
    {
        $st = $this->pdo->prepare(
            'SELECT centro_id, persona_id, importe_cents FROM saldos_disponibles_mov WHERE remesa_id = :r'
        );
        $st->execute([':r' => $remesaId]);
        $filas = $st->fetchAll();
        foreach ($filas as $row) {
            $this->pdo->prepare(
                'UPDATE saldos_disponibles SET saldo_cents = saldo_cents - :imp, updated_at = now()
                 WHERE centro_id = :c AND persona_id = :p'
            )->execute([
                ':imp' => (int) $row['importe_cents'],
                ':c' => (int) $row['centro_id'],
                ':p' => (int) $row['persona_id'],
            ]);
        }
        $this->pdo->prepare('DELETE FROM saldos_disponibles_mov WHERE remesa_id = :r')->execute([':r' => $remesaId]);
    }
}
