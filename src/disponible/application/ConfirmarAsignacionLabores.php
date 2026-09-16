<?php

declare(strict_types=1);

namespace src\disponible\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CuentaRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\disponible\domain\contracts\SaldoDisponibleRepository;
use src\disponible\domain\services\ConstructorAsientoAsignacion;
use src\disponible\domain\services\ConstructorAsientoLiquidacionCc;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\contracts\RemesaRepository;
use src\shared\domain\value_objects\Dinero;

final class ConfirmarAsignacionLabores
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly AsignacionLaboresRepository $asignaciones,
        private readonly SaldoDisponibleRepository $saldos,
        private readonly CuentaRepository $cuentas,
        private readonly AsientoRepository $asientos,
        private readonly PersonaRepository $personas,
        private readonly AsegurarCuentaCorrientePersona $asegurarCc,
        private readonly RemesaRepository $remesas,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(int $id): array
    {
        $ctx = $this->ambito->ejecutar();
        $asig = $this->asignaciones->porId($id, $ctx->centroId);
        if ($asig === null) {
            throw new InvalidArgumentException('Propuesta no encontrada');
        }
        if ($asig['estado'] !== 'borrador') {
            throw new InvalidArgumentException('Solo se puede confirmar una propuesta en borrador');
        }
        $fecha = new DateTimeImmutable('today');
        $porPersona = [];
        foreach ($asig['lineas'] as $l) {
            $porPersona[(int) $l['persona_id']][] = $l;
        }
        $this->remesas->enTransaccion(function () use ($asig, $porPersona, $ctx, $fecha): void {
            foreach ($porPersona as $personaId => $lineas) {
                $persona = $this->personas->porId($personaId);
                if ($persona === null) {
                    continue;
                }
                $this->asegurarCc->ejecutar($persona);
                $cc = $this->cuentas->personalDe($ctx->centroId, $personaId);
                if ($cc === null || $cc->id === null) {
                    throw new InvalidArgumentException('Falta la cuenta CC de ' . $persona->iniciales);
                }
                $cuenta111 = $this->cuentas->buscar($ctx->centroId, null, 'P', '111');
                if ($cuenta111 === null || $cuenta111->id === null) {
                    throw new InvalidArgumentException('Falta la cuenta 111 en el plan P del centro');
                }
                $lineasAsiento = [];
                $total = 0;
                foreach ($lineas as $l) {
                    $cuenta = $this->cuentas->buscar($ctx->centroId, null, 'P', (string) $l['codigo_maestro']);
                    if ($cuenta === null || $cuenta->id === null) {
                        throw new InvalidArgumentException('No existe la partida ' . $l['codigo_maestro']);
                    }
                    $cents = (int) $l['importe_cents'];
                    $lineasAsiento[] = ['cuenta_id' => $cuenta->id, 'importe_cents' => $cents];
                    $total += $cents;
                }
                $iniciales = strtoupper($persona->iniciales);
                $glosaLabores = sprintf('Asignación labores %s %s', $iniciales, $fecha->format('Y-m-d'));
                $asientoLabores = ConstructorAsientoAsignacion::construir(
                    $ctx->ejercicioId,
                    $personaId,
                    $fecha,
                    (int) $cuenta111->id,
                    $lineasAsiento,
                    $glosaLabores,
                );
                if ($asientoLabores !== null) {
                    $this->asientos->guardar($asientoLabores);
                }
                $glosaCc = sprintf('Liquidación saldo CC %s %s', $iniciales, $fecha->format('Y-m-d'));
                $asientoCc = ConstructorAsientoLiquidacionCc::construir(
                    $ctx->ejercicioId,
                    $personaId,
                    $fecha,
                    (int) $cuenta111->id,
                    (int) $cc->id,
                    $total,
                    $glosaCc,
                );
                if ($asientoCc !== null) {
                    $this->asientos->guardar($asientoCc);
                }
                if ($total !== 0) {
                    $this->saldos->aplicar(
                        $ctx->centroId,
                        $personaId,
                        -$total,
                        $fecha->format('Y-m-d'),
                        'asignacion',
                        $ctx->ejercicioId,
                        null,
                        (int) $asig['id'],
                        'Propuesta confirmada',
                    );
                }
            }
            $this->asignaciones->marcarConfirmada((int) $asig['id']);
        });
        $out = $this->asignaciones->porId($id, $ctx->centroId);
        if ($out === null) {
            throw new InvalidArgumentException('No se pudo releer la propuesta');
        }
        $out['total_es'] = Dinero::fromCents(
            array_sum(array_map(static fn (array $l): int => (int) $l['importe_cents'], $out['lineas']))
        )->formatEs();

        return $out;
    }
}
