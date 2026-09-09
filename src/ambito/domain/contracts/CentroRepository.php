<?php

declare(strict_types=1);

namespace src\ambito\domain\contracts;

use src\ambito\domain\entity\Centro;

interface CentroRepository
{
    /** @return list<Centro> */
    public function listar(): array;

    public function porId(int $id): ?Centro;

    public function porCodigo(string $codigo): ?Centro;

    public function guardar(Centro $centro): Centro;
}
