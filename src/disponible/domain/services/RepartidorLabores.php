<?php

declare(strict_types=1);

namespace src\disponible\domain\services;

use src\shared\domain\value_objects\Dinero;

/**
 * Reparte el disponible entre partidas 7: primero el tramo de mayor % entre
 * quienes pueden desgravar (para cumplir presupuesto), el resto a las que no.
 *
 * @phpstan-type PersonaIn array{id:int, disponible_cents:int, puede_desgravar:bool, ya_desgravado_cents:int, tope_cents?:?int}
 * @phpstan-type PartidaIn array{codigo:string, etiqueta:string, desgrava:bool, orden:int, hueco_cents:int}
 * @phpstan-type Tramo array{hasta_cents:?int, porcentaje:int}
 * @phpstan-type LineaOut array{persona_id:int, codigo:string, etiqueta:string, cents:int}
 */
final class RepartidorLabores
{
    /**
     * @param list<PersonaIn> $personas
     * @param list<PartidaIn> $partidas
     * @param list<Tramo> $tramos
     * @return array{
     *   lineas: list<LineaOut>,
     *   textos: list<array{persona_id:int, texto:string}>
     * }
     */
    public static function repartir(array $personas, array $partidas, array $tramos): array
    {
        $destSi = [];
        $destNo = [];
        foreach ($partidas as $p) {
            $fila = $p;
            $fila['hueco_cents'] = max(0, (int) $p['hueco_cents']);
            if ($p['desgrava']) {
                $destSi[] = $fila;
            } else {
                $destNo[] = $fila;
            }
        }
        usort($destSi, self::porOrden(...));
        usort($destNo, self::porOrden(...));
        if (self::huecoTotal($destSi) === 0 && self::huecoTotal($destNo) === 0) {
            $destSi = self::sinTope($destSi);
            $destNo = self::sinTope($destNo);
        }

        $disp = [];
        $ya = [];
        $puede = [];
        $tope = [];
        foreach ($personas as $p) {
            $id = (int) $p['id'];
            $disp[$id] = max(0, (int) $p['disponible_cents']);
            $ya[$id] = max(0, (int) $p['ya_desgravado_cents']);
            $puede[$id] = (bool) $p['puede_desgravar'];
            $topeRaw = $p['tope_cents'] ?? null;
            $tope[$id] = $topeRaw === null || $topeRaw === '' ? null : max(0, (int) $topeRaw);
        }

        /** @var array<int, array<string, array{etiqueta:string, cents:int}>> $acum */
        $acum = [];
        $hastaAnterior = 0;
        foreach ($tramos as $tramo) {
            if ($destSi === []) {
                break;
            }
            $capacidades = [];
            foreach ($disp as $id => $cents) {
                if (!$puede[$id] || $cents <= 0) {
                    continue;
                }
                $cap = TramosDesgravacion::capacidadTramo($tramo, $ya[$id], $hastaAnterior);
                if ($cap === PHP_INT_MAX) {
                    $cap = $cents;
                }
                if ($tope[$id] !== null) {
                    $cap = min($cap, max(0, $tope[$id] - $ya[$id]));
                }
                $cap = min($cents, $cap);
                if ($cap > 0) {
                    $capacidades[$id] = $cap;
                }
            }
            $topeHueco = self::huecoTotal($destSi);
            $aRepartir = min(array_sum($capacidades), $topeHueco);
            foreach (self::proporcional($capacidades, $aRepartir) as $id => $importe) {
                $trozos = self::echar($importe, $destSi);
                $puesto = self::sumarTrozos($trozos);
                $disp[$id] -= $puesto;
                $ya[$id] += $puesto;
                self::acumular($acum, $id, $trozos);
            }
            if ($tramo['hasta_cents'] !== null) {
                $hastaAnterior = $tramo['hasta_cents'];
            }
        }

        foreach ($disp as $id => $cents) {
            if ($cents <= 0) {
                continue;
            }
            $usarNo = $destNo !== [];
            $dest = $usarNo ? $destNo : $destSi;
            if ($dest === []) {
                continue;
            }
            $trozos = self::echar($cents, $dest);
            $puesto = self::sumarTrozos($trozos);
            $disp[$id] -= $puesto;
            self::acumular($acum, $id, $trozos);
            if ($disp[$id] > 0 && $dest !== []) {
                $dest[array_key_last($dest)]['hueco_cents'] = PHP_INT_MAX;
                $trozos2 = self::echar($disp[$id], $dest);
                $disp[$id] -= self::sumarTrozos($trozos2);
                self::acumular($acum, $id, $trozos2);
            }
            if ($usarNo) {
                $destNo = $dest;
            } else {
                $destSi = $dest;
            }
        }

        $lineas = [];
        $textos = [];
        foreach ($acum as $personaId => $porCodigo) {
            $filas = [];
            foreach ($porCodigo as $codigo => $dato) {
                if ($dato['cents'] <= 0) {
                    continue;
                }
                $fila = [
                    'persona_id' => $personaId,
                    'codigo' => (string) $codigo,
                    'etiqueta' => $dato['etiqueta'],
                    'cents' => $dato['cents'],
                ];
                $filas[] = $fila;
                $lineas[] = $fila;
            }
            if ($filas !== []) {
                $textos[] = [
                    'persona_id' => $personaId,
                    'texto' => self::texto($filas),
                ];
            }
        }

        return ['lineas' => $lineas, 'textos' => $textos];
    }

    /**
     * @param list<LineaOut> $filas
     */
    public static function texto(array $filas): string
    {
        $trozos = [];
        foreach ($filas as $f) {
            if ($f['cents'] <= 0) {
                continue;
            }
            $imp = Dinero::fromCents($f['cents'])->formatEs();
            $nombre = trim($f['codigo'] . ' ' . $f['etiqueta']);
            $trozos[] = $imp . ' € en ' . $nombre;
        }
        if ($trozos === []) {
            return '';
        }
        if (count($trozos) === 1) {
            return 'Deberías ingresar ' . $trozos[0] . '.';
        }
        $ultimo = array_pop($trozos);

        return 'Deberías ingresar ' . implode(', ', $trozos) . ' y ' . $ultimo . '.';
    }

    /**
     * @param array<int, int> $capacidades
     * @return array<int, int>
     */
    private static function proporcional(array $capacidades, int $total): array
    {
        if ($total <= 0 || $capacidades === []) {
            return [];
        }
        $sum = array_sum($capacidades);
        if ($sum <= 0) {
            return [];
        }
        $out = [];
        $asignado = 0;
        $frac = [];
        foreach ($capacidades as $id => $cap) {
            $raw = $total * $cap / $sum;
            $entero = (int) floor($raw);
            $out[$id] = $entero;
            $frac[$id] = $raw - $entero;
            $asignado += $entero;
        }
        $resto = $total - $asignado;
        arsort($frac);
        foreach (array_keys($frac) as $id) {
            if ($resto <= 0) {
                break;
            }
            $out[$id]++;
            $resto--;
        }

        return $out;
    }

    /**
     * @param list<PartidaIn> $destinos
     * @return list<array{codigo:string, etiqueta:string, cents:int}>
     */
    private static function echar(int $cents, array &$destinos): array
    {
        $trozos = [];
        foreach ($destinos as $i => $d) {
            if ($cents <= 0) {
                break;
            }
            $hueco = (int) $d['hueco_cents'];
            if ($hueco <= 0) {
                continue;
            }
            $take = min($cents, $hueco);
            $destinos[$i]['hueco_cents'] = $hueco - $take;
            $cents -= $take;
            $trozos[] = [
                'codigo' => (string) $d['codigo'],
                'etiqueta' => (string) $d['etiqueta'],
                'cents' => $take,
            ];
        }

        return $trozos;
    }

    /**
     * @param list<array{codigo:string, etiqueta:string, cents:int}> $trozos
     */
    private static function sumarTrozos(array $trozos): int
    {
        $t = 0;
        foreach ($trozos as $z) {
            $t += $z['cents'];
        }

        return $t;
    }

    /**
     * @param array<int, array<string, array{etiqueta:string, cents:int}>> $acum
     * @param list<array{codigo:string, etiqueta:string, cents:int}> $trozos
     */
    private static function acumular(array &$acum, int $personaId, array $trozos): void
    {
        foreach ($trozos as $z) {
            if ($z['cents'] <= 0) {
                continue;
            }
            $cod = $z['codigo'];
            if (!isset($acum[$personaId][$cod])) {
                $acum[$personaId][$cod] = ['etiqueta' => $z['etiqueta'], 'cents' => 0];
            }
            $acum[$personaId][$cod]['cents'] += $z['cents'];
        }
    }

    /**
     * @param list<PartidaIn> $destinos
     * @return list<PartidaIn>
     */
    private static function sinTope(array $destinos): array
    {
        foreach ($destinos as $i => $_) {
            $destinos[$i]['hueco_cents'] = PHP_INT_MAX;
        }

        return $destinos;
    }

    /**
     * @param PartidaIn $a
     * @param PartidaIn $b
     */
    private static function porOrden(array $a, array $b): int
    {
        return $a['orden'] <=> $b['orden'] ?: strcmp($a['codigo'], $b['codigo']);
    }

    /**
     * @param list<PartidaIn> $destinos
     */
    private static function huecoTotal(array $destinos): int
    {
        $t = 0;
        foreach ($destinos as $d) {
            $h = (int) $d['hueco_cents'];
            if ($h === PHP_INT_MAX) {
                return PHP_INT_MAX;
            }
            $t += $h;
        }

        return $t;
    }
}
