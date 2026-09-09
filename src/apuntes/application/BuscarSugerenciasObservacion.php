<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\asientos\domain\contracts\AsientoRepository;

/** Sugerencias de observaciones ya usadas en el libro (Entrada P/G). */
final class BuscarSugerenciasObservacion
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /**
     * @return list<array{observaciones: string, concepto_codigo: string}>
     */
    public function ejecutar(string $texto, string $cuenta, string $iniciales): array
    {
        $cuenta = strtoupper(trim($cuenta));
        if (!in_array($cuenta, ['P', 'G'], true)) {
            throw new InvalidArgumentException('Cuenta P o G');
        }
        $q = trim($texto);
        $ini = trim($iniciales);
        if (mb_strlen($q) < 2 || $ini === '') {
            return [];
        }
        $ctx = $this->ambito->ejecutar();

        return $this->asientos->sugerirPorGlosa($ctx->ejercicioId, $cuenta, $q, $ini, 12);
    }
}
