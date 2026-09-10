<?php

declare(strict_types=1);

namespace src\informes\domain\contracts;

use DateTimeImmutable;
use src\informes\domain\entity\Informe613Mes;

interface Informe613MesRepository
{
    public function buscar(int $ejercicioId, DateTimeImmutable $fechaCierre, string $cuenta): ?Informe613Mes;

    public function guardar(Informe613Mes $informe): void;
}
