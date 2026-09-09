<?php

declare(strict_types=1);

namespace src\apuntes\application;

use src\conceptos\domain\contracts\ConceptoRepository;
use src\shared\domain\value_objects\Dinero;

final class CalcularCuadreApuntesA
{
    public function __construct(
        private readonly ListarApuntes $listar,
        private readonly ConceptoRepository $conceptos,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(string $cuenta, string $iniciales, string $fecha): array
    {
        $cuenta = strtoupper(trim($cuenta));
        $iniciales = trim($iniciales);
        if ($cuenta !== 'P' || $iniciales === '') {
            return ['aplica' => false];
        }

        $naturalezas = [];
        foreach ($this->conceptos->listar($cuenta) as $concepto) {
            $naturalezas[$concepto->codigo] = $concepto->naturaleza;
        }

        $apuntes = $this->listar->ejecutar([
            'cuenta' => $cuenta,
            'origen' => 'A',
            'iniciales' => $iniciales,
        ]);

        $saldoTotal = 0;
        $saldoAntes = 0;
        $saldoFecha = 0;
        $soloGastosFecha = true;
        $hayApuntesFecha = false;

        foreach ($apuntes as $apunte) {
            $signed = $this->signedCents(
                (string) $apunte['concepto_codigo'],
                (string) $apunte['cantidad'],
                $naturalezas,
            );
            $saldoTotal += $signed;
            $f = (string) $apunte['fecha'];
            if ($f < $fecha) {
                $saldoAntes += $signed;
            } elseif ($f === $fecha) {
                $hayApuntesFecha = true;
                $saldoFecha += $signed;
                $nat = $naturalezas[(string) $apunte['concepto_codigo']] ?? '';
                if ($nat !== 'gasto') {
                    $soloGastosFecha = false;
                }
            }
        }

        $cuadrado = abs($saldoTotal) <= 0;
        $cuadradoAntes = abs($saldoAntes) <= 0;

        $sugerencia = $this->sugerencia(
            $cuadrado,
            $cuadradoAntes,
            $hayApuntesFecha,
            $soloGastosFecha,
            $saldoFecha,
            $saldoTotal,
        );

        return [
            'aplica' => true,
            'iniciales' => $iniciales,
            'fecha' => $fecha,
            'cuadrado' => $cuadrado,
            'saldo_total' => $this->fmt($saldoTotal),
            'saldo_total_es' => $this->fmtEs($saldoTotal),
            'cuadrado_antes_fecha' => $cuadradoAntes,
            'saldo_fecha' => $this->fmt($saldoFecha),
            'saldo_fecha_es' => $this->fmtEs($saldoFecha),
            'solo_gastos_fecha' => $soloGastosFecha && $hayApuntesFecha,
            'sugerencia' => $sugerencia,
        ];
    }

    /**
     * @param array<string, string> $naturalezas
     */
    private function signedCents(string $codigo, string $cantidad, array $naturalezas): int
    {
        $nat = $naturalezas[$codigo] ?? '';
        if ($nat !== 'gasto' && $nat !== 'ingreso') {
            return 0;
        }
        $cents = Dinero::fromInput($cantidad)->toCents();

        return $nat === 'gasto' ? $cents : -$cents;
    }

    /**
     * @return array<string, string>|null
     */
    private function sugerencia(
        bool $cuadrado,
        bool $cuadradoAntes,
        bool $hayApuntesFecha,
        bool $soloGastosFecha,
        int $saldoFecha,
        int $saldoTotal,
    ): ?array {
        if ($cuadrado) {
            return null;
        }

        if ($cuadradoAntes && $hayApuntesFecha && $soloGastosFecha && $saldoFecha > 0) {
            return $this->sugerencia111($saldoFecha, 'solo_gastos_fecha');
        }

        if ($saldoTotal > 0) {
            return $this->sugerencia111($saldoTotal, 'saldo_positivo');
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function sugerencia111(int $cents, string $motivo): array
    {
        $importe = Dinero::fromCents($cents);

        return [
            'concepto_codigo' => '111',
            'cantidad' => $importe->toString(),
            'cantidad_es' => $importe->formatEs(),
            'motivo' => $motivo,
        ];
    }

    private function fmt(int $cents): string
    {
        return Dinero::fromCents($cents)->toString();
    }

    private function fmtEs(int $cents): string
    {
        return Dinero::fromCents($cents)->formatEs();
    }
}
