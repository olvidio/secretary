<?php

declare(strict_types=1);

namespace src\asientos\domain\services;

use InvalidArgumentException;
use src\ambito\domain\entity\Cuenta;
use src\apuntes\domain\entity\Apunte;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\entity\Movimiento;
use src\personas\domain\entity\Persona;

/**
 * Traduce apuntes del modelo Excel a asientos de partida doble (Fase 3 OLA 1).
 * Servicio de dominio puro: sin PDO; recibe cuentas y personas ya resueltas.
 */
final class TraductorApuntesAAsientos
{
    /**
     * @param list<Apunte> $apuntes
     * @param callable(string): ?Persona $personaPorIniciales iniciales en cualquier capitalización
     * @param callable(string, string): Cuenta $cuentaConcepto (libro, codigoConcepto)
     * @param callable(string, string): Cuenta $cuentaTesoreria (libro, codigoMaestro CAJA|BANCO)
     * @param callable(int): Cuenta $cuentaPersonalDe (personaId)
     * @param callable(): Cuenta $cuentaDeudoresVivienda
     * @return array{asientos: list<Asiento>, omitidos_concepto_9: int, traspasos_fusionados: int}
     */
    public function traducir(
        int $ejercicioId,
        array $apuntes,
        callable $personaPorIniciales,
        callable $cuentaConcepto,
        callable $cuentaTesoreria,
        callable $cuentaPersonalDe,
        callable $cuentaDeudoresVivienda,
        string $origenAsiento = 'import',
    ): array {
        $omitidos9 = 0;
        $fusionados = 0;
        $asientos = [];
        $traspasos = [];
        $normales = [];

        foreach ($apuntes as $apunte) {
            if ($apunte->conceptoCodigo === '9') {
                ++$omitidos9;
                continue;
            }
            if (in_array($apunte->conceptoCodigo, ['41', '42'], true)) {
                $traspasos[] = $apunte;
                continue;
            }
            $normales[] = $apunte;
        }

        $consumidos = [];
        foreach ($this->agruparTraspasos($traspasos) as $grupo) {
            $representante = $grupo[0];
            foreach ($grupo as $miembro) {
                if ($miembro->id !== null) {
                    $consumidos[$miembro->id] = true;
                }
            }
            if (count($grupo) >= 2) {
                ++$fusionados;
            }
            $asientos[] = $this->crearTraspaso(
                $ejercicioId,
                $representante,
                $cuentaTesoreria,
                $personaPorIniciales,
                $origenAsiento,
            );
        }

        foreach ($normales as $apunte) {
            if ($apunte->id !== null && isset($consumidos[$apunte->id])) {
                continue;
            }
            $asientos[] = $this->crearAsientoNormal(
                $ejercicioId,
                $apunte,
                $personaPorIniciales,
                $cuentaConcepto,
                $cuentaTesoreria,
                $cuentaPersonalDe,
                $cuentaDeudoresVivienda,
                $origenAsiento,
            );
        }

        return [
            'asientos' => $asientos,
            'omitidos_concepto_9' => $omitidos9,
            'traspasos_fusionados' => $fusionados,
        ];
    }

    /**
     * @param list<Apunte> $traspasos
     * @return list<list<Apunte>>
     */
    public function gruposDeTraspaso(array $traspasos): array
    {
        return $this->agruparTraspasos($traspasos);
    }

    /**
     * @param list<Apunte> $traspasos
     * @return list<list<Apunte>>
     */
    private function agruparTraspasos(array $traspasos): array
    {
        $grupos = [];
        $asignados = [];

        foreach ($traspasos as $apunte) {
            if ($apunte->id !== null && isset($asignados[$apunte->id])) {
                continue;
            }
            if ($apunte->parId !== null) {
                $pareja = $this->buscarPorId($traspasos, $apunte->parId);
                if ($pareja !== null && $this->sonParTraspaso($apunte, $pareja)) {
                    $grupos[] = [$apunte, $pareja];
                    if ($apunte->id !== null) {
                        $asignados[$apunte->id] = true;
                    }
                    if ($pareja->id !== null) {
                        $asignados[$pareja->id] = true;
                    }
                    continue;
                }
            }
        }

        foreach ($traspasos as $apunte) {
            if ($apunte->id !== null && isset($asignados[$apunte->id])) {
                continue;
            }
            $grupo = [$apunte];
            $pareja = $this->buscarParejaPorCampos($traspasos, $apunte, $asignados);
            if ($pareja !== null) {
                $grupo[] = $pareja;
                if ($pareja->id !== null) {
                    $asignados[$pareja->id] = true;
                }
            }
            if ($apunte->id !== null) {
                $asignados[$apunte->id] = true;
            }
            $grupos[] = $grupo;
        }

        return $grupos;
    }

    /** @param list<Apunte> $apuntes */
    private function buscarPorId(array $apuntes, int $id): ?Apunte
    {
        foreach ($apuntes as $apunte) {
            if ($apunte->id === $id) {
                return $apunte;
            }
        }

        return null;
    }

    private function sonParTraspaso(Apunte $a, Apunte $b): bool
    {
        return $a->fecha->format('Y-m-d') === $b->fecha->format('Y-m-d')
            && $a->cuenta === $b->cuenta
            && $a->conceptoCodigo === $b->conceptoCodigo
            && $a->cantidad->compare($b->cantidad) === 0
            && (($a->origen === 'B' && $b->origen === 'C') || ($a->origen === 'C' && $b->origen === 'B'));
    }

    /**
     * @param list<Apunte> $traspasos
     * @param array<int, true> $asignados
     */
    private function buscarParejaPorCampos(array $traspasos, Apunte $apunte, array $asignados): ?Apunte
    {
        $otroOrigen = $apunte->origen === 'B' ? 'C' : 'B';
        foreach ($traspasos as $candidato) {
            if ($candidato->id !== null && isset($asignados[$candidato->id])) {
                continue;
            }
            if ($candidato === $apunte) {
                continue;
            }
            if ($candidato->fecha->format('Y-m-d') !== $apunte->fecha->format('Y-m-d')) {
                continue;
            }
            if ($candidato->cuenta !== $apunte->cuenta) {
                continue;
            }
            if ($candidato->conceptoCodigo !== $apunte->conceptoCodigo) {
                continue;
            }
            if ($candidato->cantidad->compare($apunte->cantidad) !== 0) {
                continue;
            }
            if ($candidato->origen !== $otroOrigen) {
                continue;
            }

            return $candidato;
        }

        return null;
    }

    private function crearTraspaso(
        int $ejercicioId,
        Apunte $apunte,
        callable $cuentaTesoreria,
        callable $personaPorIniciales,
        string $origenAsiento,
    ): Asiento {
        $libro = $apunte->cuenta;
        $importe = abs($apunte->cantidad->toCents());
        $caja = $cuentaTesoreria($libro, 'CAJA');
        $banco = $cuentaTesoreria($libro, 'BANCO');

        if ($apunte->conceptoCodigo === '41') {
            $movimientos = [
                new Movimiento(null, 1, $caja->id, null, $importe, 0),
                new Movimiento(null, 2, $banco->id, null, 0, $importe),
            ];
        } else {
            $movimientos = [
                new Movimiento(null, 1, $banco->id, null, $importe, 0),
                new Movimiento(null, 2, $caja->id, null, 0, $importe),
            ];
        }
        if ($apunte->cantidad->isNegative()) {
            $movimientos = $this->invertirMovimientos($movimientos);
        }

        return new Asiento(
            null,
            $ejercicioId,
            $libro,
            null,
            $apunte->fecha,
            $apunte->observaciones,
            'traspaso',
            $this->origenDeApunte($apunte, $origenAsiento),
            $this->resolverPersonaId($apunte, $personaPorIniciales),
            $movimientos,
            $apunte->conceptoCodigo,
            null,
            $apunte->fecha,
        );
    }

    private function crearAsientoNormal(
        int $ejercicioId,
        Apunte $apunte,
        callable $personaPorIniciales,
        callable $cuentaConcepto,
        callable $cuentaTesoreria,
        callable $cuentaPersonalDe,
        callable $cuentaDeudoresVivienda,
        string $origenAsiento,
    ): Asiento {
        $libro = $apunte->cuenta;
        $concepto = $this->resolverCuentaConcepto($cuentaConcepto, $libro, $apunte->conceptoCodigo);
        $importe = abs($apunte->cantidad->toCents());
        $persona = $this->resolverPersona($apunte, $personaPorIniciales);
        $personaId = $persona?->id;

        $tipoAsiento = match (true) {
            $apunte->conceptoCodigo === '32' => 'apertura',
            $apunte->esCierre => 'cierre',
            default => 'normal',
        };

        $movimientos = $this->construirMovimientos(
            $apunte,
            $concepto,
            $importe,
            $cuentaTesoreria,
            $cuentaPersonalDe,
            $cuentaDeudoresVivienda,
            $personaId,
        );
        if ($apunte->cantidad->isNegative()) {
            $movimientos = $this->invertirMovimientos($movimientos);
        }

        return new Asiento(
            null,
            $ejercicioId,
            $libro,
            null,
            $apunte->fecha,
            $apunte->observaciones,
            $tipoAsiento,
            $this->origenDeApunte($apunte, $origenAsiento),
            $personaId,
            $movimientos,
            $apunte->conceptoCodigo,
            null,
            $apunte->fecha,
        );
    }

    /**
     * @return list<Movimiento>
     */
    private function construirMovimientos(
        Apunte $apunte,
        Cuenta $concepto,
        int $importe,
        callable $cuentaTesoreria,
        callable $cuentaPersonalDe,
        callable $cuentaDeudoresVivienda,
        ?int $personaId,
    ): array {
        $libro = $apunte->cuenta;
        $origen = $apunte->origen;
        $tipoConcepto = $concepto->tipo;

        if ($tipoConcepto === 'ingreso' || $tipoConcepto === 'patrimonio') {
            $contrapartida = match ($origen) {
                'C' => $cuentaTesoreria($libro, 'CAJA'),
                'B' => $cuentaTesoreria($libro, 'BANCO'),
                'A' => $this->contrapartidaOrigenA($apunte, $personaId, $cuentaPersonalDe, $cuentaDeudoresVivienda),
                default => throw new InvalidArgumentException('Origen no válido: ' . $origen),
            };

            return [
                new Movimiento(null, 1, $contrapartida->id, $this->personaIdEnMovimiento($contrapartida, $personaId), $importe, 0),
                new Movimiento(null, 2, $concepto->id, null, 0, $importe),
            ];
        }

        if ($tipoConcepto === 'gasto') {
            $contrapartida = match ($origen) {
                'C' => $cuentaTesoreria($libro, 'CAJA'),
                'B' => $cuentaTesoreria($libro, 'BANCO'),
                'A' => $this->contrapartidaOrigenA($apunte, $personaId, $cuentaPersonalDe, $cuentaDeudoresVivienda),
                default => throw new InvalidArgumentException('Origen no válido: ' . $origen),
            };

            return [
                new Movimiento(null, 1, $concepto->id, null, $importe, 0),
                new Movimiento(null, 2, $contrapartida->id, $this->personaIdEnMovimiento($contrapartida, $personaId), 0, $importe),
            ];
        }

        if ($tipoConcepto === 'puente') {
            $caja = $cuentaTesoreria($libro, 'CAJA');
            $banco = $cuentaTesoreria($libro, 'BANCO');
            if ($apunte->conceptoCodigo === '41') {
                return [
                    new Movimiento(null, 1, $caja->id, null, $importe, 0),
                    new Movimiento(null, 2, $banco->id, null, 0, $importe),
                ];
            }

            return [
                new Movimiento(null, 1, $banco->id, null, $importe, 0),
                new Movimiento(null, 2, $caja->id, null, 0, $importe),
            ];
        }

        throw new InvalidArgumentException(
            sprintf('Tipo de cuenta no soportado para concepto %s/%s: %s', $libro, $apunte->conceptoCodigo, $tipoConcepto)
        );
    }

    private function contrapartidaOrigenA(
        Apunte $apunte,
        ?int $personaId,
        callable $cuentaPersonalDe,
        callable $cuentaDeudoresVivienda,
    ): Cuenta {
        if ($apunte->cuenta === 'P') {
            if ($personaId === null) {
                throw new InvalidArgumentException(
                    'Origen A en P requiere iniciales de persona (concepto ' . $apunte->conceptoCodigo . ')'
                );
            }

            return $cuentaPersonalDe($personaId);
        }

        return $cuentaDeudoresVivienda();
    }

    private function resolverCuentaConcepto(callable $cuentaConcepto, string $libro, string $codigo): Cuenta
    {
        $cuenta = $cuentaConcepto($libro, $codigo);
        if ($cuenta->id === null) {
            throw new InvalidArgumentException('Cuenta de concepto sin id persistido');
        }
        if (!$cuenta->imputable) {
            throw new InvalidArgumentException(
                sprintf('La cuenta %s/%s no es imputable', $libro, $codigo)
            );
        }

        return $cuenta;
    }

    private function resolverPersona(Apunte $apunte, callable $personaPorIniciales): ?Persona
    {
        if ($apunte->iniciales === null || trim($apunte->iniciales) === '') {
            if ($apunte->cuenta === 'P' && $apunte->origen === 'A' && $apunte->conceptoCodigo !== '32') {
                throw new InvalidArgumentException(
                    'Origen A en P requiere iniciales (concepto ' . $apunte->conceptoCodigo . ')'
                );
            }

            return null;
        }

        $persona = $personaPorIniciales($apunte->iniciales);
        if ($persona === null) {
            throw new InvalidArgumentException('Persona no encontrada para iniciales: ' . $apunte->iniciales);
        }

        return $persona;
    }

    private function resolverPersonaId(Apunte $apunte, callable $personaPorIniciales): ?int
    {
        return $this->resolverPersona($apunte, $personaPorIniciales)?->id;
    }

    private function personaIdEnMovimiento(Cuenta $cuenta, ?int $personaId): ?int
    {
        if ($cuenta->tipo === 'personal' && $cuenta->personaId !== null) {
            return $cuenta->personaId;
        }

        return $personaId;
    }

    private function origenDeApunte(Apunte $apunte, string $origenAsiento): string
    {
        if ($apunte->esCierre) {
            return 'cierre';
        }

        return $origenAsiento;
    }

    /**
     * @param list<Movimiento> $movimientos
     * @return list<Movimiento>
     */
    private function invertirMovimientos(array $movimientos): array
    {
        $invertidos = [];
        foreach ($movimientos as $mov) {
            $invertidos[] = new Movimiento(
                $mov->id,
                $mov->orden,
                $mov->cuentaId,
                $mov->personaId,
                $mov->haberCents,
                $mov->debeCents,
            );
        }

        return $invertidos;
    }
}
