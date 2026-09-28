<?php

declare(strict_types=1);

namespace src\plan\domain\services;

/** Destinos variables del plan H16s (códigos 42 a 54). El 41 queda fijo en el plan. */
final class DestinosCentroSg
{
    public static function esVariable(string $codigo): bool
    {
        return preg_match('/^(4[2-9]|5[0-4])$/', $codigo) === 1;
    }

    public static function siguiente(array $usados): ?string
    {
        $tomados = array_fill_keys($usados, true);
        for ($n = 42; $n <= 54; $n++) {
            $codigo = (string) $n;
            if (!isset($tomados[$codigo])) {
                return $codigo;
            }
        }

        return null;
    }

    /**
     * Quita los destinos 42–54 que el centro no ha nombrado y pone la etiqueta del centro.
     *
     * @param list<array<string, mixed>> $conceptos
     * @param array<string, string>|null $nombrados null si el centro no es H16s
     * @return list<array<string, mixed>>
     */
    public static function aplicar(array $conceptos, ?array $nombrados): array
    {
        if ($nombrados === null) {
            return $conceptos;
        }
        $out = [];
        foreach ($conceptos as $c) {
            $codigo = (string) ($c['codigo'] ?? '');
            if (($c['cuenta'] ?? '') === 'G' && self::esVariable($codigo)) {
                $nombre = trim((string) ($nombrados[$codigo] ?? ''));
                if ($nombre === '' || $nombre === $codigo) {
                    continue;
                }
                $c['nombre'] = $nombre;
                $c['descripcion'] = $codigo . ' ' . $nombre;
                $c['etiqueta'] = $c['descripcion'];
            }
            $out[] = $c;
        }

        return $out;
    }
}
