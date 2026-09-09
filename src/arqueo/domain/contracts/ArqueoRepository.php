<?php

declare(strict_types=1);

namespace src\arqueo\domain\contracts;

use DateTimeImmutable;
use src\arqueo\domain\entity\Arqueo;

interface ArqueoRepository
{
    public function ultimo(string $cuenta): ?Arqueo;

    public function ultimoPorFisica(int $cuentaFisicaId): ?Arqueo;

    public function guardar(Arqueo $arqueo): Arqueo;
}
