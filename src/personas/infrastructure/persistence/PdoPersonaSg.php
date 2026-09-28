<?php

declare(strict_types=1);

namespace src\personas\infrastructure\persistence;

use PDO;

/** Grupo y clase (s / cp) de los nombres de un centro sg. No forma parte del libro H16n. */
final class PdoPersonaSg
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int, array{grupo:int, clase:string}> */
    public function deCentro(int $centroId): array
    {
        $st = $this->pdo->prepare(
            'SELECT s.persona_id, s.grupo, s.clase
             FROM persona_sg s
             INNER JOIN personas p ON p.id = s.persona_id
             WHERE p.centro_id = :c'
        );
        $st->execute([':c' => $centroId]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(int) $row['persona_id']] = [
                'grupo' => (int) $row['grupo'],
                'clase' => (string) $row['clase'],
            ];
        }

        return $out;
    }

    public function guardar(int $personaId, int $grupo, string $clase): void
    {
        $clase = $clase === 'cp' ? 'cp' : 's';
        $grupo = max(1, $grupo);
        $st = $this->pdo->prepare(
            'INSERT INTO persona_sg (persona_id, grupo, clase) VALUES (:p, :g, :c)
             ON CONFLICT (persona_id) DO UPDATE SET grupo = excluded.grupo, clase = excluded.clase'
        );
        $st->execute([':p' => $personaId, ':g' => $grupo, ':c' => $clase]);
    }
}
