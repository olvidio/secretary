<?php

declare(strict_types=1);

namespace src\ambito\infrastructure\persistence;

use PDO;
use src\ambito\domain\contracts\PobladorCentro;

final class PdoPobladorCentro implements PobladorCentro
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ejecutar(int $centroId): void
    {
        AmbitoSeeder::poblarLibros($this->pdo, $centroId);
    }
}
