<?php

declare(strict_types=1);

namespace src\personal\infrastructure\persistence;

use PDO;
use src\personal\domain\contracts\RemanenteRepository;

final class PdoRemanenteRepository implements RemanenteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function dePersona(int $personaId): int
    {
        $st = $this->pdo->prepare('SELECT remanente_cents FROM personas WHERE id = :id');
        $st->execute([':id' => $personaId]);
        $row = $st->fetch();
        if (!is_array($row) || $row['remanente_cents'] === null) {
            return 0;
        }

        return (int) $row['remanente_cents'];
    }

    public function guardar(int $personaId, int $cents): void
    {
        $st = $this->pdo->prepare('UPDATE personas SET remanente_cents = :c WHERE id = :id');
        $st->execute([':c' => $cents, ':id' => $personaId]);
    }
}
