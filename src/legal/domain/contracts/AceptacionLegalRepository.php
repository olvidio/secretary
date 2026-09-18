<?php

declare(strict_types=1);

namespace src\legal\domain\contracts;

use src\legal\domain\entity\AceptacionLegal;

interface AceptacionLegalRepository
{
    public function registrar(AceptacionLegal $aceptacion): void;
}
