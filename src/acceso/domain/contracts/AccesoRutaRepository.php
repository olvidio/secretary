<?php

declare(strict_types=1);

namespace src\acceso\domain\contracts;

interface AccesoRutaRepository
{
    public function ambitoDe(string $clase, string $metodoPhp): ?string;
}
