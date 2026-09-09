<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\Remesa;
use src\remesas\domain\services\HashRemesa;

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
            throw new InvalidArgumentException('El ejercicio de ese mes está cerrado; no se puede enviar');
        }
        $ctx = $preview['ctx'];
        $lineas = $preview['lineas'];
        $hash = HashRemesa::deLineas($lineas);
        $enviada = $this->remesas->enviadaDe($ctx->personaId, $ejercicio->id, $anio, $mes);
        if ($enviada !== null && $enviada->hashContenido === $hash) {
            return $enviada;
        }
        $notaFinal = $nota !== '' ? $nota : null;

        return $this->remesas->enTransaccion(function () use ($ctx, $ejercicio, $anio, $mes, $lineas, $hash, $notaFinal, $enviada): Remesa {
            if ($enviada !== null && $enviada->id !== null) {
                $this->remesas->marcarEstado($enviada->id, 'sustituida', true);
            }
            $version = $this->remesas->maxVersion($ctx->personaId, (int) $ejercicio->id, $anio, $mes) + 1;

            return $this->remesas->guardarConLineas(new Remesa(
                null,
                $ctx->personaId,
                $ctx->centroId,
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
            ));
        });
    }
}
