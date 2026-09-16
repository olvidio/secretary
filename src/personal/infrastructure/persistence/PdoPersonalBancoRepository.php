<?php

declare(strict_types=1);

namespace src\personal\infrastructure\persistence;

use PDO;
use src\personal\domain\contracts\PersonalBancoRepository;

final class PdoPersonalBancoRepository implements PersonalBancoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function dePersona(int $personaId): ?string
    {
        $st = $this->pdo->prepare('SELECT banco_csv FROM personas WHERE id = :id');
        $st->execute([':id' => $personaId]);
        $row = $st->fetch();
        if (!is_array($row) || $row['banco_csv'] === null || $row['banco_csv'] === '') {
            return null;
        }

        return (string) $row['banco_csv'];
    }

    public function guardar(int $personaId, string $banco): void
    {
        $st = $this->pdo->prepare('UPDATE personas SET banco_csv = :b WHERE id = :id');
        $st->execute([':b' => $banco, ':id' => $personaId]);
    }
}
