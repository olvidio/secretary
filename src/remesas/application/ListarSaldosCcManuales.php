<?php

declare(strict_types=1);

namespace src\remesas\application;

use src\ambito\application\ResolverAmbitoActual;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\contracts\SaldoCcManualRepository;

final class ListarSaldosCcManuales
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly PersonaRepository $personas,
        private readonly SaldoCcManualRepository $saldos,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function ejecutar(): array
    {
        $ctx = $this->ambito->ejecutar();
        $out = [];
        foreach ($this->saldos->listarDeEjercicio($ctx->centroId, $ctx->ejercicioId) as $saldo) {
            $persona = $this->personas->porId($saldo->personaId);
            $out[] = $saldo->toArray() + [
                'iniciales' => $persona?->iniciales ?? '',
                'persona' => $persona?->nombreCompleto() ?? '',
            ];
        }

        return $out;
    }
}
