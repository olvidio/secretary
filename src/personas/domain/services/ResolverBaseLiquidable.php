<?php

declare(strict_types=1);

namespace src\personas\domain\services;

/**
 * Base liquidable para el tope de donativos: lo escrito a mano, si no el 111
 * de la previsión personal, si no el ingreso 111 proyectado a fin de ejercicio.
 *
 * @phpstan-type Estimacion array{cents:int, origen:string}
 */
final class ResolverBaseLiquidable
{
    public const ORIGEN_MANUAL = 'manual';
    public const ORIGEN_PREVISION = 'prevision_111';
    public const ORIGEN_PROYECTADO = 'proyectado_111';

    /**
     * @return Estimacion|null
     */
    public static function de(?int $manualCents, ?int $prevision111Cents, int $proyectado111Cents): ?array
    {
        if ($manualCents !== null && $manualCents > 0) {
            return ['cents' => $manualCents, 'origen' => self::ORIGEN_MANUAL];
        }
        if ($prevision111Cents !== null && $prevision111Cents > 0) {
            return ['cents' => $prevision111Cents, 'origen' => self::ORIGEN_PREVISION];
        }
        if ($proyectado111Cents > 0) {
            return ['cents' => $proyectado111Cents, 'origen' => self::ORIGEN_PROYECTADO];
        }

        return null;
    }
}
