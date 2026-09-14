<?php

declare(strict_types=1);

namespace src\personal\domain\value_objects;

/** Fila normalizada de un extracto CSV, lista para asentar. */
final class LineaExtractoBanco
{
    public function __construct(
        public readonly string $fecha,
        public readonly int $cents,
        public readonly string $concepto,
        public readonly string $huella,
        public readonly string $categoriaOrigen = '',
    ) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            throw new \InvalidArgumentException('Fecha de extracto no válida');
        }
        if ($cents === 0) {
            throw new \InvalidArgumentException('El importe no puede ser cero');
        }
        if ($huella === '') {
            throw new \InvalidArgumentException('Falta la huella del movimiento');
        }
    }

    public function sentido(): string
    {
        return $this->cents > 0 ? 'ingreso' : 'gasto';
    }

    public function centsAbs(): int
    {
        return abs($this->cents);
    }
}
