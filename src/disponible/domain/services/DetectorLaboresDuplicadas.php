<?php

declare(strict_types=1);

namespace src\disponible\domain\services;

/**
 * Partida 7 apuntada dos veces: una asignación de destinos 7 anotada al
 * confirmar (flujo antiguo) y otro apunte de la misma persona, partida e
 * importe que no es de asignación (normalmente la remesa con la transferencia
 * real). Cada apunte se empareja una sola vez.
 *
 * @phpstan-type Fila array{asiento_id:int, numero:int, fecha:string, tipo:string, origen:string, persona_id:?int, iniciales:string, codigo:string, cents:int}
 * @phpstan-type Par array{persona_id:int, iniciales:string, codigo:string, cents:int, asignacion:Fila, otro:Fila}
 */
final class DetectorLaboresDuplicadas
{
    /**
     * @param list<Fila> $filas
     * @return list<Par>
     */
    public static function detectar(array $filas): array
    {
        $usados = [];
        $pares = [];
        foreach ($filas as $asig) {
            if (!self::esAsignacion($asig) || $asig['persona_id'] === null || $asig['cents'] <= 0) {
                continue;
            }
            foreach ($filas as $i => $otro) {
                if (isset($usados[$i])
                    || $otro['origen'] === 'asignacion'
                    || $otro['persona_id'] !== $asig['persona_id']
                    || $otro['codigo'] !== $asig['codigo']
                    || $otro['cents'] !== $asig['cents']) {
                    continue;
                }
                $usados[$i] = true;
                $pares[] = [
                    'persona_id' => $asig['persona_id'],
                    'iniciales' => $asig['iniciales'],
                    'codigo' => $asig['codigo'],
                    'cents' => $asig['cents'],
                    'asignacion' => $asig,
                    'otro' => $otro,
                ];
                break;
            }
        }

        return $pares;
    }

    /**
     * Céntimos de partidas 7 de un asiento (todas sus filas).
     *
     * @param list<Fila> $filas
     */
    public static function totalAsiento(array $filas, int $asientoId): int
    {
        $total = 0;
        foreach ($filas as $f) {
            if ($f['asiento_id'] === $asientoId) {
                $total += $f['cents'];
            }
        }

        return $total;
    }

    /** @param Fila $fila */
    private static function esAsignacion(array $fila): bool
    {
        return $fila['origen'] === 'asignacion' && $fila['tipo'] === 'normal';
    }
}
