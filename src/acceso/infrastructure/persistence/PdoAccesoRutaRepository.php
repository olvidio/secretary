<?php

declare(strict_types=1);

namespace src\acceso\infrastructure\persistence;

use PDO;
use src\acceso\domain\contracts\AccesoRutaRepository;

final class PdoAccesoRutaRepository implements AccesoRutaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ambitoDe(string $clase, string $metodoPhp): ?string
    {
        $st = $this->pdo->prepare(
            'SELECT ambito FROM rutas_acceso WHERE clase = :c AND metodo_php = :m'
        );
        $st->execute([':c' => $clase, ':m' => $metodoPhp]);
        $v = $st->fetchColumn();

        return is_string($v) ? $v : null;
    }
}
