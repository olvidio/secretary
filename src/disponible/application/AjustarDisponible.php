<?php

declare(strict_types=1);

namespace src\disponible\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\disponible\domain\contracts\SaldoDisponibleRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class AjustarDisponible
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly SaldoDisponibleRepository $saldos,
        private readonly PersonaRepository $personas,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function ejecutar(array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $personaId = (int) ($datos['persona_id'] ?? 0);
        $persona = $this->personas->porId($personaId);
        if ($persona === null || $persona->centroId !== $ctx->centroId) {
            throw new InvalidArgumentException('Persona no encontrada');
        }
        $nuevo = Dinero::fromInput((string) ($datos['saldo'] ?? '0'))->toCents();
        $actual = $this->saldos->saldoDe($ctx->centroId, $personaId);
        $delta = $nuevo - $actual;
        $nota = trim((string) ($datos['nota'] ?? ''));
        $saldo = $this->saldos->aplicar(
            $ctx->centroId,
            $personaId,
            $delta,
            date('Y-m-d'),
            'ajuste',
            $ctx->ejercicioId,
            null,
            null,
            $nota !== '' ? $nota : 'Ajuste manual',
        );
        $d = Dinero::fromCents($saldo);

        return [
            'persona_id' => $personaId,
            'saldo_cents' => $saldo,
            'saldo' => $d->toString(),
            'saldo_es' => $d->formatEs(),
        ];
    }
}
