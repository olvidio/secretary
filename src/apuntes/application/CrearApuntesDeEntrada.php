<?php

declare(strict_types=1);

namespace src\apuntes\application;

use src\apuntes\domain\services\ContrapartidasGastoGeneral;
use src\asientos\domain\value_objects\FilaApunteExcel;
use src\ambito\application\ResolverAmbitoActual;
use src\conceptos\application\ResolverConceptosCentro;
use src\personas\domain\contracts\PersonaRepository;

final class CrearApuntesDeEntrada
{
    public function __construct(
        private readonly CrearApunte $crear,
        private readonly ResolverConceptosCentro $conceptos,
        private readonly ResolverAmbitoActual $ambito,
        private readonly PersonaRepository $personas,
        private readonly ContrapartidasGastoGeneral $contrapartidas,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<FilaApunteExcel>
     */
    public function ejecutar(array $datos): array
    {
        $lineas = $this->lineasContrapartida($datos);
        if ($lineas === null) {
            return $this->crear->ejecutar($datos);
        }

        $out = [];
        $ultima = count($lineas) - 1;
        foreach ($lineas as $i => $linea) {
            $payload = [
                'fecha' => $datos['fecha'] ?? '',
                'cuenta' => $linea['cuenta'],
                'origen' => $linea['origen'],
                'iniciales' => $datos['iniciales'] ?? '',
                'concepto_codigo' => $linea['concepto_codigo'],
                'observaciones' => $linea['observaciones'] ?? '',
                'cantidad' => $datos['cantidad'] ?? '',
            ];
            if (!empty($datos['es_cierre'])) {
                $payload['es_cierre'] = $datos['es_cierre'];
            }
            if ($i === $ultima) {
                $payload['fecha_imputacion'] = $datos['fecha_imputacion'] ?? '';
                if (isset($datos['cuenta_fisica_id']) && $datos['cuenta_fisica_id'] !== '') {
                    $payload['cuenta_fisica_id'] = $datos['cuenta_fisica_id'];
                }
            }
            if (!empty($datos['remesa_id'])) {
                $payload['remesa_id'] = (int) $datos['remesa_id'];
            }
            foreach ($this->crear->ejecutar($payload) as $fila) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<array{cuenta:string,origen:string,concepto_codigo:string,observaciones:?string}>|null
     */
    private function lineasContrapartida(array $datos): ?array
    {
        if (empty($datos['contrapartidas'])) {
            return null;
        }
        $cuenta = strtoupper(trim((string) ($datos['cuenta'] ?? '')));
        $codigo = trim((string) ($datos['concepto_codigo'] ?? $datos['concepto'] ?? ''));
        $concepto = $this->conceptos->buscar($this->ambito->ejecutar()->centroId, $cuenta, $codigo);
        $iniciales = trim((string) ($datos['iniciales'] ?? ''));
        $persona = $iniciales !== '' ? $this->personas->porIniciales($iniciales) : null;
        $obsRaw = (string) ($datos['observaciones'] ?? '');

        return $this->contrapartidas->lineas(
            $cuenta,
            strtoupper(trim((string) ($datos['origen'] ?? 'A'))),
            $codigo,
            $concepto?->naturaleza ?? '',
            $iniciales,
            $obsRaw === '' ? null : $obsRaw,
        );
    }
}
