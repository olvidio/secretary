<?php

declare(strict_types=1);

namespace src\importacion\infrastructure\persistence;

use PDO;
use src\importacion\domain\contracts\ImportEjecucionRepository;

final class PdoImportEjecucionRepository implements ImportEjecucionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function registrar(
        int $centroId,
        int $ejercicioId,
        string $fichero,
        string $sha256,
        bool $dryRun,
        int $altas,
        int $cambios,
        int $bajas,
        int $omitidos,
    ): void {
        $st = $this->pdo->prepare(
            'INSERT INTO import_ejecuciones
                (centro_id, ejercicio_id, fichero, sha256, dry_run, altas, cambios, bajas, omitidos)
             VALUES (:c, :ej, :f, :sha, :dry, :altas, :cambios, :bajas, :omit)'
        );
        $st->execute([
            ':c' => $centroId,
            ':ej' => $ejercicioId,
            ':f' => $fichero,
            ':sha' => $sha256,
            ':dry' => (int) $dryRun,
            ':altas' => $altas,
            ':cambios' => $cambios,
            ':bajas' => $bajas,
            ':omit' => $omitidos,
        ]);
    }
}
