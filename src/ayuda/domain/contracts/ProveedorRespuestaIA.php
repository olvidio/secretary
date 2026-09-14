<?php

declare(strict_types=1);

namespace src\ayuda\domain\contracts;

use RuntimeException;

interface ProveedorRespuestaIA
{
    /**
     * Texto tal cual lo devuelve el modelo, sin interpretar.
     *
     * @throws RuntimeException si el proveedor no responde o devuelve un error
     */
    public function responder(string $instruccion, string $pregunta): string;
}
