<?php

declare(strict_types=1);

namespace src\apuntes\application;

use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\personas\domain\contracts\PersonaRepository;

final class ListarApuntes
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly PersonaRepository $personas,
        private readonly ProyectorAsientoAFilaExcel $proyector,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /**
     * @param array<string, mixed> $filtros
     * @return list<array<string, mixed>>
     */
    public function ejecutar(array $filtros = []): array
    {
        $contexto = $this->ambito->ejecutar();
        $mapaCuentas = [];
        foreach ($this->cuentas->listarDeCentro($contexto->centroId) as $cuenta) {
            if ($cuenta->id !== null) {
                $mapaCuentas[$cuenta->id] = $cuenta;
            }
        }
        $mapaPersonas = [];
        foreach ($this->personas->listar() as $persona) {
            if ($persona->id !== null) {
                $mapaPersonas[$persona->id] = $persona;
            }
        }

        $out = [];
        $vistosPar = [];
        foreach ($this->asientos->listar($contexto->ejercicioId, $filtros) as $asiento) {
            $par = $this->parPeriodificacion($asiento);
            if ($par !== null) {
                $clave = $this->clavePar($par['imputacion'], $par['tesoreria']);
                if (isset($vistosPar[$clave])) {
                    continue;
                }
                $vistosPar[$clave] = true;
                $fila = $this->proyector->proyectarPar(
                    $par['imputacion'],
                    $par['tesoreria'],
                    $mapaCuentas,
                    $mapaPersonas,
                );
            } else {
                $fila = $this->proyector->proyectar($asiento, $mapaCuentas, $mapaPersonas);
            }
            if (!empty($filtros['origen']) && $fila->origen !== strtoupper((string) $filtros['origen'])) {
                continue;
            }
            if (!empty($filtros['concepto']) && $fila->conceptoCodigo !== $filtros['concepto']) {
                continue;
            }
            $out[] = $fila->toArray();
        }

        return $out;
    }

    /**
     * @return array{imputacion: Asiento, tesoreria: Asiento}|null
     */
    private function parPeriodificacion(Asiento $asiento): ?array
    {
        if ($asiento->asientoParId === null) {
            return null;
        }
        if ($asiento->tipo !== 'periodificacion' && $asiento->tipo !== 'normal') {
            return null;
        }
        $par = $this->asientos->porId($asiento->asientoParId);
        if ($par === null) {
            return null;
        }
        if ($asiento->tipo === 'periodificacion' && $par->tipo === 'normal') {
            return ['imputacion' => $par, 'tesoreria' => $asiento];
        }
        if ($asiento->tipo === 'normal' && $par->tipo === 'periodificacion') {
            return ['imputacion' => $asiento, 'tesoreria' => $par];
        }

        return null;
    }

    private function clavePar(Asiento $imputacion, Asiento $tesoreria): string
    {
        return (string) ($imputacion->id ?? 0) . ':' . (string) ($tesoreria->id ?? 0);
    }
}
