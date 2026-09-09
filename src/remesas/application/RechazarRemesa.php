<?php

declare(strict_types=1);

namespace src\remesas\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\asientos\domain\contracts\AsientoRepository;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\Remesa;

final class RechazarRemesa
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly RemesaRepository $remesas,
        private readonly AsientoRepository $asientos,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public function ejecutar(int $id, array $datos = []): Remesa
    {
        $remesa = $this->remesas->porId($id);
        $ctx = $this->ambito->ejecutar();
        if ($remesa === null || $remesa->centroId !== $ctx->centroId || $remesa->id === null) {
            throw new InvalidArgumentException('Remesa no encontrada');
        }
        if (!in_array($remesa->estado, ['enviada', 'aceptada'], true)) {
            throw new InvalidArgumentException('Solo se puede rechazar una remesa enviada o aceptada');
        }
        $nota = trim((string) ($datos['nota'] ?? ''));
        $this->remesas->enTransaccion(function () use ($remesa, $nota): void {
            if ($remesa->estado === 'aceptada') {
                $this->asientos->borrarPorRemesaId((int) $remesa->id);
            }
            $this->remesas->marcarEstado((int) $remesa->id, 'rechazada', true);
            if ($nota !== '') {
                $previa = $remesa->nota !== null && $remesa->nota !== '' ? $remesa->nota . "\n" : '';
                $this->remesas->actualizarNota((int) $remesa->id, $previa . $nota);
            }
        });
        $out = $this->remesas->porId((int) $remesa->id);
        if ($out === null) {
            throw new InvalidArgumentException('No se pudo releer la remesa');
        }

        return $out;
    }
}
