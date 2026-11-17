<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\domain\contracts\BancoCentroImportRepository;

/**
 * Movimientos del extracto cuyo apunte se borró en Apuntes. Conservan la
 * huella (no vuelven a entrar al reimportar); recuperarlos los devuelve a
 * pendientes de categorizar.
 */
final class DescartadosBancoCentro
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly BancoCentroImportRepository $filas,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        return $this->filas->descartadas($this->ambito->ejecutar()->centroId);
    }

    public function recuperar(int $filaId): void
    {
        if (!$this->filas->recuperar($this->ambito->ejecutar()->centroId, $filaId)) {
            throw new InvalidArgumentException(_('Ese movimiento no está entre los descartados'));
        }
    }
}
