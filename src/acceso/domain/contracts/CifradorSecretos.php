<?php

declare(strict_types=1);

namespace src\acceso\domain\contracts;

interface CifradorSecretos
{
    public function cifrar(string $claro): string;

    public function descifrar(string $paquete): string;
}
