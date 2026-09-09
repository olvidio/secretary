<?php

declare(strict_types=1);

namespace src\ambito\domain\value_objects;

/**
 * Ámbito de la sesión actual (D3, docs/dev/plan_ampliaciones.md). Se inyecta en
 * los repositorios/casos de uso que ya pueden filtrar por él; ver
 * docs/dev/ambito.md para el inventario de qué repositorios lo usan todavía y
 * cuáles no tienen aún columna de ámbito.
 */
final class ContextoActual
{
    public function __construct(
        public readonly int $centroId,
        public readonly int $ejercicioId,
        public readonly ?int $personaId = null,
    ) {
    }
}
