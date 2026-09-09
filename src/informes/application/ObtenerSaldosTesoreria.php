<?php

declare(strict_types=1);

namespace src\informes\application;

use DateTimeImmutable;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaFisicaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\shared\domain\value_objects\Dinero;

final class ObtenerSaldosTesoreria
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CuentaFisicaRepository $fisicas,
        private readonly ConfiguracionRepository $config,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(?string $hasta): array
    {
        $cfg = $this->config->get();
        $fechaHasta = $hasta ? new DateTimeImmutable($hasta) : $cfg->fechaCierre;
        $desde = $cfg->fechaInicio->format('Y-m-d');
        $hastaStr = $fechaHasta->format('Y-m-d');

        $contexto = $this->ambito->ejecutar();
        $saldos = $this->asientos->saldosPorCuenta(
            $contexto->centroId,
            $contexto->ejercicioId,
            $desde,
            $hastaStr,
        );

        $porFisicaLibro = [];
        foreach ($saldos as $row) {
            if ($row['tipo'] !== 'tesoreria' || $row['cuenta_fisica_id'] === null) {
                continue;
            }
            $fid = $row['cuenta_fisica_id'];
            $porFisicaLibro[$fid][$row['libro']] = Dinero::fromCents($row['saldo_cents']);
        }

        $out = [];
        foreach ($this->fisicas->listarActivasDeCentro($contexto->centroId) as $fisica) {
            if ($fisica->id === null) {
                continue;
            }
            $saldoP = $porFisicaLibro[$fisica->id]['P'] ?? Dinero::zero();
            $saldoG = $porFisicaLibro[$fisica->id]['G'] ?? Dinero::zero();
            $fisico = $saldoP->add($saldoG);
            $out[] = [
                'id' => $fisica->id,
                'tipo' => $fisica->tipo,
                'nombre' => $fisica->nombre,
                'iban' => $fisica->iban,
                'saldo_p' => $saldoP->toString(),
                'saldo_p_es' => $saldoP->formatEs(),
                'saldo_g' => $saldoG->toString(),
                'saldo_g_es' => $saldoG->formatEs(),
                'saldo_fisico' => $fisico->toString(),
                'saldo_fisico_es' => $fisico->formatEs(),
            ];
        }

        return $out;
    }
}
