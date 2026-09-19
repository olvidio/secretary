<?php

declare(strict_types=1);

namespace src\envio_dl\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\envio_dl\domain\contracts\EnvioDlRepository;
use src\envio_dl\domain\services\RepartidorEnvioDl;
use src\informes\application\CalcularSaldos;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class ProponerEnvioDl
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly PersonaRepository $personas,
        private readonly CalcularSaldos $calcularSaldos,
        private readonly EnvioDlRepository $envios,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $total = Dinero::fromInput((string) ($datos['importe'] ?? ''));
        if ($total->isZero() || $total->isNegative()) {
            throw new InvalidArgumentException(_("Indica un importe positivo"));
        }
        if ($total->toCents() % 100 !== 0) {
            throw new InvalidArgumentException(_("El importe debe ser un número entero de euros (sin céntimos)"));
        }
        $personas = $this->personas->listarDeCentro($ctx->centroId);
        $saldosData = $this->calcularSaldos->ejecutar(null);
        $saldoCajaPorIniciales = [];
        foreach ($saldosData['por_persona'] as $row) {
            $saldoCajaPorIniciales[strtolower((string) $row['iniciales'])] = Dinero::fromInput(
                (string) ($row['saldo_apuntes_c'] ?? '0'),
            )->toCents();
        }
        $saldoPorPersona = [];
        $saldoEsPorPersona = [];
        foreach ($personas as $p) {
            if ($p->id === null) {
                continue;
            }
            $ini = strtolower(trim($p->iniciales));
            $cents = $saldoCajaPorIniciales[$ini] ?? 0;
            $saldoPorPersona[$p->id] = $cents;
            $saldoEsPorPersona[$p->id] = Dinero::fromCents($cents)->formatEs();
        }
        $lineas = RepartidorEnvioDl::repartir($total, $personas, $saldoPorPersona);
        $nombres = [];
        foreach ($personas as $p) {
            if ($p->id !== null) {
                $nombres[$p->id] = $p;
            }
        }
        $porPersona = [];
        foreach ($lineas as $l) {
            $pid = $l['persona_id'];
            $p = $nombres[$pid] ?? null;
            if ($p === null) {
                continue;
            }
            $porPersona[] = [
                'persona_id' => $pid,
                'iniciales' => $p->iniciales,
                'nombre' => $p->nombreCompleto(),
                'saldo_es' => $saldoEsPorPersona[$pid] ?? '0,00',
                'importe_cents' => $l['importe_cents'],
                'importe_es' => Dinero::fromCents($l['importe_cents'])->formatEs(),
            ];
        }
        $id = null;
        if ($porPersona === []) {
            $this->envios->borrarBorradores($ctx->centroId, $ctx->ejercicioId);
        } else {
            $id = $this->envios->guardarBorrador(
                $ctx->centroId,
                $ctx->ejercicioId,
                $total->toCents(),
                $lineas,
            );
        }

        return [
            'envio_id' => $id,
            'importe_total_es' => $total->formatEs(),
            'hasta' => $saldosData['hasta'],
            'personas' => $porPersona,
        ];
    }
}
