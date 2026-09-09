<?php

declare(strict_types=1);

namespace src\importacion\domain\contracts;

use src\importacion\domain\entity\FilaImportada;

interface ImportFilaRepository
{
    public function contarDeEjercicio(int $ejercicioId): int;

    /** @return list<FilaImportada> */
    public function listarDeEjercicio(int $ejercicioId): array;

    public function guardar(FilaImportada $fila): void;

    public function borrarClave(int $ejercicioId, string $hoja, int $fila): void;
}
