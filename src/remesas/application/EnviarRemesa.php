<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\Remesa;
use src\remesas\domain\services\CalculoDisponibleRemesa;
use src\remesas\domain\services\HashRemesa;
use src\shared\domain\value_objects\Dinero;

final class EnviarRemesa
{
    public function __construct(
        private readonly ResolverMesRemesa $mes,
        private readonly RemesaRepository $remesas,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos): Remesa
    {
        $anio = (int) ($datos['anio'] ?? 0);
        $mes = (int) ($datos['mes'] ?? 0);
        $nota = trim((string) ($datos['nota'] ?? ''));
        $preview = $this->mes->ejecutar($anio, $mes);
        $ejercicio = $preview['ejercicio'];
        if ($ejercicio->estado !== 'abierto' || $ejercicio->id === null) {
            throw new InvalidArgumentException(_("El ejercicio de ese mes está cerrado; no se puede enviar"));
        }
        $personaId = (int) $preview['destino_persona_id'];
        $centroId = (int) $preview['destino_centro_id'];
        $lineas = $preview['lineas'];
        $saldo = array_key_exists('saldo_tesoreria', $datos) && $datos['saldo_tesoreria'] !== '' && $datos['saldo_tesoreria'] !== null
            ? Dinero::fromInput((string) $datos['saldo_tesoreria'])->toCents()
            : (int) $preview['tesoreria_cents'];
        $tesoreria = CalculoDisponibleRemesa::cents($saldo, (int) $preview['remanente_cents']);
        $hash = HashRemesa::deLineas($lineas, $tesoreria);
        $enviada = $this->remesas->enviadaDe($personaId, $ejercicio->id, $anio, $mes);
        if ($enviada !== null && $enviada->hashContenido === $hash) {
            return $enviada;
        }
        $notaFinal = $nota !== '' ? $nota : null;

        return $this->remesas->enTransaccion(function () use ($personaId, $centroId, $ejercicio, $anio, $mes, $lineas, $hash, $notaFinal, $enviada, $tesoreria): Remesa {
            if ($enviada !== null && $enviada->id !== null) {
                $this->remesas->marcarEstado($enviada->id, 'sustituida', true);
            }
            $version = $this->remesas->maxVersion($personaId, (int) $ejercicio->id, $anio, $mes) + 1;

            return $this->remesas->guardarConLineas(new Remesa(
                null,
                $personaId,
                $centroId,
                (int) $ejercicio->id,
                $anio,
                $mes,
                $version,
                'enviada',
                $hash,
                null,
                null,
                $notaFinal,
                $lineas,
                $tesoreria,
            ));
        });
    }
}
