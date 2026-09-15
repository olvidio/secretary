<?php

declare(strict_types=1);

namespace src\remesas\domain\services;

use src\remesas\domain\entity\RemesaLinea;

/** SHA-256 del contenido canónico (código + importe + tesorería) ordenado. */
final class HashRemesa
{
    /**
     * @param list<RemesaLinea> $lineas
     */
    public static function deLineas(array $lineas, ?int $saldoTesoreriaCents = null): string
    {
        $pares = [];
        foreach ($lineas as $linea) {
            $pares[] = [
                'codigo' => $linea->codigoMaestro,
                'importe' => $linea->importeCents,
            ];
        }
        usort($pares, static fn (array $a, array $b): int => $a['codigo'] <=> $b['codigo']);
        $payload = ['lineas' => $pares, 'tesoreria' => $saldoTesoreriaCents];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
    }
}
