<?php

declare(strict_types=1);

namespace src\acceso\domain\contracts;

use src\personas\domain\entity\Persona;

interface LibroPersonalIdentidadPort
{
    public function ejecutar(int $identidadId): ?Persona;
}
