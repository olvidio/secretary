<?php

declare(strict_types=1);

namespace src\disponible\application;

use src\ambito\application\ResolverAmbitoActual;
use src\disponible\domain\contracts\SaldoDisponibleRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class ListarDisponibles
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly SaldoDisponibleRepository $saldos,
        private readonly PersonaRepository $personas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $porPersona = [];
        foreach ($this->saldos->listarDeCentro($ctx->centroId) as $fila) {
            $porPersona[$fila['persona_id']] = $fila['saldo_cents'];
        }
        $out = [];
        foreach ($this->personas->listarDeCentro($ctx->centroId) as $p) {
            if ($p->id === null) {
                continue;
            }
            $cents = $porPersona[$p->id] ?? 0;
            $d = Dinero::fromCents($cents);
            $out[] = [
                'persona_id' => $p->id,
                'iniciales' => $p->iniciales,
                'nombre' => $p->nombreCompleto(),
                'puede_desgravar' => $p->puedeDesgravar,
                'saldo_cents' => $cents,
                'saldo' => $d->toString(),
                'saldo_es' => $d->formatEs(),
            ];
        }

        return $out;
    }
}
