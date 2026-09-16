<?php

declare(strict_types=1);

namespace src\envio_dl\infrastructure\persistence;

use PDO;
use src\envio_dl\domain\contracts\EnvioDlRepository;

final class PdoEnvioDlRepository implements EnvioDlRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param list<array{persona_id:int, importe_cents:int}> $lineas */
    public function guardarBorrador(int $centroId, int $ejercicioId, int $totalCents, array $lineas): int
    {
        $this->borrarBorradores($centroId, $ejercicioId);
        $ins = $this->pdo->prepare(
            "INSERT INTO envios_dl (centro_id, ejercicio_id, importe_total_cents, estado)
             VALUES (:c, :e, :t, 'borrador') RETURNING id"
        );
        $ins->execute([':c' => $centroId, ':e' => $ejercicioId, ':t' => $totalCents]);
        $id = (int) $ins->fetchColumn();
        $linea = $this->pdo->prepare(
            'INSERT INTO envios_dl_lineas (envio_id, persona_id, importe_cents)
             VALUES (:envio, :p, :i)'
        );
        foreach ($lineas as $l) {
            $linea->execute([
                ':envio' => $id,
                ':p' => $l['persona_id'],
                ':i' => $l['importe_cents'],
            ]);
        }

        return $id;
    }

    public function borrarBorradores(int $centroId, int $ejercicioId): void
    {
        $this->pdo->prepare(
            "DELETE FROM envios_dl
             WHERE centro_id = :c AND ejercicio_id = :e AND estado = 'borrador'"
        )->execute([':c' => $centroId, ':e' => $ejercicioId]);
    }

    /** @return array<string, mixed>|null */
    public function porId(int $id, int $centroId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM envios_dl WHERE id = :id AND centro_id = :c');
        $st->execute([':id' => $id, ':c' => $centroId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $lineas = $this->pdo->prepare(
            'SELECT * FROM envios_dl_lineas WHERE envio_id = :id ORDER BY persona_id'
        );
        $lineas->execute([':id' => $id]);
        $row['lineas'] = $lineas->fetchAll(PDO::FETCH_ASSOC);

        return $row;
    }

    public function marcarConfirmada(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE envios_dl SET estado = 'confirmada', confirmada_at = now() WHERE id = :id"
        )->execute([':id' => $id]);
    }
}
