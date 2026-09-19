<?php

declare(strict_types=1);

namespace src\informes\application;

use DateTimeImmutable;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\application\ListarApuntes;
use src\apuntes\domain\services\SaldoCuadreApuntes;
use src\asientos\domain\contracts\AsientoRepository;
use src\conceptos\application\ResolverConceptosCentro;
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
        private readonly ListarApuntes $listarApuntes,
        private readonly ResolverConceptosCentro $conceptos,
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
        foreach ($this->personas->listarDeCentro($contexto->centroId) as $p) {
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
            if (
                $row['tipo'] === 'personal'
                && $row['libro'] === 'P'
                && $row['persona_id'] !== null
                && $row['codigo_maestro'] === '9'
            ) {
                $persona = $personasPorId[$row['persona_id']] ?? null;
                if ($persona !== null) {
                    $porPersonaMap[$persona->iniciales] = $saldo;
                    $saldoA = $saldoA->add($saldo);
                }
            }
        }

        $naturalezas = [];
        foreach ($this->conceptos->listar($contexto->centroId, 'P') as $concepto) {
            $naturalezas[$concepto['codigo']] = $concepto['naturaleza'];
        }
        $personaIdPorIniciales = [];
        foreach ($personasPorId as $pid => $persona) {
            $personaIdPorIniciales[strtolower($persona->iniciales)] = $pid;
        }
        $saldosApuntes = SaldoCuadreApuntes::porPersonaYOrigen(
            $this->listarApuntes->ejecutar(['cuenta' => 'P', 'hasta' => $hastaStr]),
            $naturalezas,
            $personaIdPorIniciales,
        );

        $porPersona = [];
        foreach ($this->personas->listarDeCentro($contexto->centroId) as $p) {
            $cc = $porPersonaMap[$p->iniciales] ?? Dinero::zero();
            if ($iniciales !== null && $p->iniciales !== $iniciales) {
                continue;
            }
            $apuntes = $p->id !== null ? ($saldosApuntes[$p->id] ?? ['A' => 0, 'B' => 0, 'C' => 0]) : ['A' => 0, 'B' => 0, 'C' => 0];
            // Cuadre interno: gasto suma, ingreso resta. En pantalla: positivo = más ingresos que gastos.
            $saldoApuntesA = Dinero::fromCents(-$apuntes['A']);
            $saldoApuntesB = Dinero::fromCents(-$apuntes['B']);
            $saldoApuntesC = Dinero::fromCents(-$apuntes['C']);
            $porPersona[] = [
                'iniciales' => $p->iniciales,
                'nombre' => $p->nombreCompleto(),
                'saldo_a' => $cc->toString(),
                'saldo_a_es' => $cc->formatEs(),
                'saldo_apuntes_a' => $saldoApuntesA->toString(),
                'saldo_apuntes_a_es' => $saldoApuntesA->formatEs(),
                'saldo_apuntes_b' => $saldoApuntesB->toString(),
                'saldo_apuntes_b_es' => $saldoApuntesB->formatEs(),
                'saldo_apuntes_c' => $saldoApuntesC->toString(),
                'saldo_apuntes_c_es' => $saldoApuntesC->formatEs(),
                'saldo_cc' => $cc->toString(),
                'saldo_cc_es' => $cc->formatEs(),
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
