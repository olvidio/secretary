<?php

declare(strict_types=1);

namespace src\importacion\domain\services;

use src\apuntes\domain\entity\Apunte;

/** Hash canónico del contenido de una fila de Talonarios (D8). */
final class HashFilaImportacion
{
    public static function de(Apunte $apunte): string
    {
        $payload = implode("\n", [
            $apunte->fecha->format('Y-m-d'),
            $apunte->cuenta,
            $apunte->origen,
            strtolower(trim((string) $apunte->iniciales)),
            $apunte->conceptoCodigo,
            $apunte->cantidad->toString(),
            (string) $apunte->observaciones,
        ]);

        return hash('sha256', $payload);
    }
}
