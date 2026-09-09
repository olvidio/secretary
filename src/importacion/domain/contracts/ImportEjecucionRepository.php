<?php

declare(strict_types=1);

namespace src\importacion\domain\contracts;

interface ImportEjecucionRepository
{
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
    ): void;
}
