<?php

declare(strict_types=1);

namespace src\remesas\domain\contracts;

use src\remesas\domain\entity\SaldoCcManual;

interface SaldoCcManualRepository
{
    /** Inserta o sustituye el saldo de esa persona y mes. */
    public function guardar(SaldoCcManual $saldo): SaldoCcManual;

    public function porId(int $id): ?SaldoCcManual;

    public function borrar(int $id): void;

    /** @return list<SaldoCcManual> ordenados por mes descendente */
    public function listarDeEjercicio(int $centroId, int $ejercicioId): array;
}
