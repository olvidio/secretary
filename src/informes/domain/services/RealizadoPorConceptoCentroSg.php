<?php

declare(strict_types=1);

namespace src\informes\domain\services;

use src\ambito\domain\contracts\CuentaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\services\ProyectorAsientoAFilaExcel;
use src\personas\domain\contracts\PersonaRepository;

/**
 * Realizado del 613 G-D (H16s): movimientos en cuentas de gasto/ingreso más, si quedaron
 * asientos antiguos tipo traspaso sin línea de concepto, el importe atribuido al código
 * 41–54 que muestra el talonario (H16s no usa traspaso caja/banco).
 */
final class RealizadoPorConceptoCentroSg
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CuentaRepository $cuentas,
        private readonly PersonaRepository $personas,
        private readonly ProyectorAsientoAFilaExcel $proyector,
    ) {
    }

    /**
     * @return array<string, int> concepto => cents (misma convención que AsientoRepository::realizadoPorConcepto)
     */
    public function ejecutar(
        int $centroId,
        int $ejercicioId,
        string $desde,
        string $hasta,
    ): array {
        $realizado = $this->asientos->realizadoPorConcepto(
            $centroId,
            $ejercicioId,
            'G',
            $desde,
            $hasta,
        );

        $mapaCuentas = [];
        foreach ($this->cuentas->listarDeCentro($centroId) as $cuenta) {
            if ($cuenta->id !== null) {
                $mapaCuentas[$cuenta->id] = $cuenta;
            }
        }
        $mapaPersonas = [];
        foreach ($this->personas->listarDeCentro($centroId) as $persona) {
            if ($persona->id !== null) {
                $mapaPersonas[$persona->id] = $persona;
            }
        }

        foreach ($this->asientos->listar($ejercicioId, [
            'cuenta' => 'G',
            'desde' => $desde,
            'hasta' => $hasta,
        ]) as $asiento) {
            if ($asiento->tipo !== 'traspaso' || $asiento->origen === 'banco') {
                continue;
            }
            foreach ($this->proyector->proyectarFilas($asiento, $mapaCuentas, $mapaPersonas, true) as $fila) {
                $cod = $fila->conceptoCodigo;
                if ($cod === '' || $cod === '9') {
                    continue;
                }
                $realizado[$cod] = ($realizado[$cod] ?? 0) + $fila->cantidad->toCents();
            }
        }

        return $realizado;
    }
}
