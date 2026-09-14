<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use src\personal\domain\contracts\PersonalCierreRepository;

final class GuardarCierrePersonalDefecto
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly PersonalCierreRepository $cierres,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array{dia_cierre: ?int, dia_habil: bool}
     */
    public function ejecutar(array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $diaRaw = $datos['dia_cierre'] ?? null;
        $dia = null;
        if ($diaRaw !== null && $diaRaw !== '') {
            $dia = (int) $diaRaw;
            if ($dia < 1 || $dia > 28) {
                throw new InvalidArgumentException('El día de cierre debe estar entre 1 y 28, o vacío');
            }
        }
        $diaHabil = !empty($datos['dia_habil']);
        $this->cierres->guardarDefecto($ctx->personaId, $dia, $diaHabil);

        return ['dia_cierre' => $dia, 'dia_habil' => $diaHabil];
    }
}
