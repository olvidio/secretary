<?php

declare(strict_types=1);

namespace src\presupuestos\infrastructure\persistence;

use PDO;
use src\presupuestos\domain\contracts\PrevisionPersonalRepository;
use src\presupuestos\domain\entity\LineaPrevisionPersonal;

final class PdoPrevisionPersonalRepository implements PrevisionPersonalRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listarDePersona(int $ejercicioId, int $personaId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM prevision_personal_lineas
             WHERE ejercicio_id = :ej AND persona_id = :p
             ORDER BY concepto_codigo'
        );
        $st->execute([':ej' => $ejercicioId, ':p' => $personaId]);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function listarDeEjercicio(int $ejercicioId): array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM prevision_personal_lineas
             WHERE ejercicio_id = :ej
             ORDER BY persona_id, concepto_codigo'
        );
        $st->execute([':ej' => $ejercicioId]);

        return array_map($this->hydrate(...), $st->fetchAll());
    }

    public function reemplazarDePersona(int $ejercicioId, int $personaId, array $lineas): void
    {
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare(
                'DELETE FROM prevision_personal_lineas WHERE ejercicio_id = :ej AND persona_id = :p'
            );
            $del->execute([':ej' => $ejercicioId, ':p' => $personaId]);
            $ins = $this->pdo->prepare(
                'INSERT INTO prevision_personal_lineas
                    (ejercicio_id, persona_id, concepto_codigo, previsto_cents)
                 VALUES (:ej, :p, :c, :v)'
            );
            foreach ($lineas as $linea) {
                $ins->execute([
                    ':ej' => $linea->ejercicioId,
                    ':p' => $linea->personaId,
                    ':c' => $linea->conceptoCodigo,
                    ':v' => $linea->previstoCents,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): LineaPrevisionPersonal
    {
        return new LineaPrevisionPersonal(
            (int) $row['ejercicio_id'],
            (int) $row['persona_id'],
            (string) $row['concepto_codigo'],
            (int) $row['previsto_cents'],
        );
    }
}
