<?php

declare(strict_types=1);

namespace src\apuntes\application;

use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\services\SaldoCuadreApuntes;
use src\apuntes\domain\services\SugerenciaCuadreApuntesA;
use src\conceptos\application\ResolverConceptosCentro;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class CalcularCuadreApuntesA
{
    public function __construct(
        private readonly ListarApuntes $listar,
        private readonly ResolverConceptosCentro $conceptos,
        private readonly ResolverAmbitoActual $ambito,
        private readonly PersonaRepository $personas,
        private readonly ConfiguracionRepository $config,
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

        $contexto = $this->ambito->ejecutar();
        $naturalezas = [];
        foreach ($this->conceptos->listar($contexto->centroId, $cuenta) as $concepto) {
            $naturalezas[$concepto['codigo']] = $concepto['naturaleza'];
        }

        $naturalezasG = [];
        foreach ($this->conceptos->listar($contexto->centroId, 'G') as $concepto) {
            $naturalezasG[$concepto['codigo']] = $concepto['naturaleza'];
        }

        $apuntes = $this->listar->ejecutar([
            'cuenta' => $cuenta,
            'origen' => 'A',
            'iniciales' => $iniciales,
        ]);

        $apuntesG = $this->listar->ejecutar([
            'cuenta' => 'G',
            'origen' => 'A',
            'iniciales' => $iniciales,
        ]);

        $saldoTotal = 0;
        $saldoAntes = 0;
        $saldoFecha = 0;
        $soloGastosFecha = true;
        $hayApuntesFecha = false;

        foreach ($apuntes as $apunte) {
            $signed = SaldoCuadreApuntes::signedCents(
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

        $persona = $this->personas->porInicialesDeCentro($contexto->centroId, $iniciales);
        $cfg = $this->config->get();
        $aportaVivienda = $persona === null || $persona->viviendaAportaGenerales;

        $sugerencia = SugerenciaCuadreApuntesA::sugerir(
            $cuadrado,
            $cuadradoAntes,
            $hayApuntesFecha,
            $soloGastosFecha,
            $saldoFecha,
            $saldoTotal,
            $fecha,
            $apuntesG,
            $naturalezasG,
            $aportaVivienda,
            $cfg->tipoCierre,
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

    private function fmt(int $cents): string
    {
        return Dinero::fromCents($cents)->toString();
    }

    private function fmtEs(int $cents): string
    {
        return Dinero::fromCents($cents)->formatEs();
    }
}
