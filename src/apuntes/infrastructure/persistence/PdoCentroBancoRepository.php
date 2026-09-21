<?php

declare(strict_types=1);

namespace src\apuntes\infrastructure\persistence;

use PDO;
use src\apuntes\domain\contracts\CentroBancoRepository;

final class PdoCentroBancoRepository implements CentroBancoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function deCentro(int $centroId): ?string
    {
        $st = $this->pdo->prepare('SELECT banco_csv FROM centros WHERE id = :id');
        $st->execute([':id' => $centroId]);
        $row = $st->fetch();
        if (!is_array($row) || $row['banco_csv'] === null || $row['banco_csv'] === '') {
            return null;
        }

        return (string) $row['banco_csv'];
    }

    public function guardar(int $centroId, string $banco): void
    {
        $st = $this->pdo->prepare('UPDATE centros SET banco_csv = :b WHERE id = :id');
        $st->execute([':b' => $banco, ':id' => $centroId]);
    }
}
