<?php

declare(strict_types=1);

namespace src\informes\application;

use DateTimeImmutable;
use src\ambito\application\ResolverAmbitoActual;
use src\asientos\domain\contracts\AsientoRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class CalcularSaldos
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly ConfiguracionRepository $config,
        private readonly PersonaRepository $personas,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(?string $hasta, ?string $iniciales = null): array
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

        $caja = Dinero::zero();
        $banco = Dinero::zero();
        $saldoA = Dinero::zero();
        $porPersonaMap = [];
        $personasPorId = [];
        foreach ($this->personas->listar() as $p) {
            if ($p->id !== null) {
                $personasPorId[$p->id] = $p;
            }
        }

        foreach ($saldos as $row) {
            $saldo = Dinero::fromCents($row['saldo_cents']);
            if ($row['tipo'] === 'tesoreria' && $row['codigo_maestro'] === 'CAJA' && $row['libro'] !== 'X') {
                $caja = $caja->add($saldo);
            }
            if ($row['tipo'] === 'tesoreria' && $row['codigo_maestro'] === 'BANCO' && $row['libro'] !== 'X') {
                $banco = $banco->add($saldo);
            }
            if ($row['tipo'] === 'personal' && $row['libro'] === 'P' && $row['persona_id'] !== null) {
                $persona = $personasPorId[$row['persona_id']] ?? null;
                if ($persona !== null) {
                    $porPersonaMap[$persona->iniciales] = $saldo;
                    $saldoA = $saldoA->add($saldo);
                }
            }
        }

        $porPersona = [];
        foreach ($this->personas->listar() as $p) {
            $s = $porPersonaMap[$p->iniciales] ?? Dinero::zero();
            if ($iniciales !== null && $p->iniciales !== $iniciales) {
                continue;
            }
            $porPersona[] = [
                'iniciales' => $p->iniciales,
                'nombre' => $p->nombreCompleto(),
                'saldo_a' => $s->toString(),
                'saldo_a_es' => $s->formatEs(),
            ];
        }

        return [
            'hasta' => $hastaStr,
            'caja' => $caja->toString(),
            'caja_es' => $caja->formatEs(),
            'banco' => $banco->toString(),
            'banco_es' => $banco->formatEs(),
            'saldo_a' => $saldoA->toString(),
            'saldo_a_es' => $saldoA->formatEs(),
            'por_persona' => $porPersona,
        ];
    }
}
