<?php

declare(strict_types=1);

namespace src\disponible\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\disponible\domain\contracts\SaldoDisponibleRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\mensajes\application\RefrescarBandejaDePersona;
use src\remesas\domain\contracts\RemesaRepository;
use src\shared\domain\value_objects\Dinero;

final class ConfirmarAsignacionLabores
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly AsignacionLaboresRepository $asignaciones,
        private readonly SaldoDisponibleRepository $saldos,
        private readonly PersonaRepository $personas,
        private readonly RemesaRepository $remesas,
        private readonly RefrescarBandejaDePersona $refrescarBandeja,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $id): array
    {
        $ctx = $this->ambito->ejecutar();
        $asig = $this->asignaciones->porId($id, $ctx->centroId);
        if ($asig === null) {
            throw new InvalidArgumentException(_("Propuesta no encontrada"));
        }
        if ($asig['estado'] !== 'borrador') {
            throw new InvalidArgumentException(_("Solo se puede confirmar una propuesta en borrador"));
        }
        $fecha = new DateTimeImmutable('today');
        $porPersona = [];
        foreach ($asig['lineas'] as $l) {
            $porPersona[(int) $l['persona_id']][] = $l;
        }
        $this->remesas->enTransaccion(function () use ($asig, $porPersona, $ctx, $fecha): void {
            foreach ($porPersona as $personaId => $lineas) {
                $total = array_sum(array_map(static fn (array $l): int => (int) $l['importe_cents'], $lineas));
                if ($total !== 0) {
                    $this->saldos->aplicar(
                        $ctx->centroId,
                        (int) $personaId,
                        -$total,
                        $fecha->format('Y-m-d'),
                        'asignacion',
                        $ctx->ejercicioId,
                        null,
                        (int) $asig['id'],
                        'Instrucción destinos 7 (pendiente de remesa)',
                    );
                }
            }
            $this->asignaciones->marcarConfirmada((int) $asig['id']);
        });
        foreach (array_keys($porPersona) as $personaId) {
            $this->refrescarBandeja->ejecutar((int) $personaId);
        }
        $out = $this->asignaciones->porId($id, $ctx->centroId);
        if ($out === null) {
            throw new InvalidArgumentException(_("No se pudo releer la propuesta"));
        }
        $out['total_es'] = Dinero::fromCents(
            array_sum(array_map(static fn (array $l): int => (int) $l['importe_cents'], $out['lineas']))
        )->formatEs();

        return $out;
    }
}
