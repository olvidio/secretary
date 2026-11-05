<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\EjercicioRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\remesas\domain\contracts\SaldoCcManualRepository;
use src\remesas\domain\entity\SaldoCcManual;
use src\shared\domain\value_objects\Dinero;

/**
 * El secretario anota a mano el concepto 90 (caja+banco) de una persona a fin
 * de un mes del ejercicio actual. Si ya había uno para ese mes, lo sustituye.
 */
final class RegistrarSaldoCcManual
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly EjercicioRepository $ejercicios,
        private readonly PersonaRepository $personas,
        private readonly SaldoCcManualRepository $saldos,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): SaldoCcManual
    {
        $ctx = $this->ambito->ejecutar();
        $personaId = (int) ($datos['persona_id'] ?? 0);
        $persona = $personaId > 0 ? $this->personas->porId($personaId) : null;
        if ($persona === null || $persona->centroId !== $ctx->centroId) {
            throw new InvalidArgumentException(_("Persona no encontrada en este centro"));
        }
        $anio = (int) ($datos['anio'] ?? 0);
        $mes = (int) ($datos['mes'] ?? 0);
        $ejercicio = $this->ejercicios->porId($ctx->ejercicioId);
        if ($ejercicio === null) {
            throw new InvalidArgumentException(_("Ejercicio sin identificador"));
        }
        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException(_("Mes no válido"));
        }
        $mesClave = sprintf('%04d-%02d', $anio, $mes);
        if ($mesClave < $ejercicio->fechaInicio->format('Y-m') || $mesClave > $ejercicio->fechaFin->format('Y-m')) {
            throw new InvalidArgumentException(_("El mes está fuera del ejercicio actual"));
        }
        $raw = trim((string) ($datos['saldo'] ?? ''));
        if ($raw === '') {
            throw new InvalidArgumentException(_("Indique el saldo de caja y banco"));
        }
        $saldo = Dinero::fromInput($raw);
        $nota = trim((string) ($datos['nota'] ?? ''));

        return $this->saldos->guardar(new SaldoCcManual(
            null,
            $ctx->centroId,
            $ctx->ejercicioId,
            $personaId,
            $anio,
            $mes,
            $saldo->toCents(),
            $nota !== '' ? mb_substr($nota, 0, 200) : null,
        ));
    }
}
