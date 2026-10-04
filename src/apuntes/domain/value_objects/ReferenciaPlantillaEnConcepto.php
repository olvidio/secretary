<?php

declare(strict_types=1);

namespace src\apuntes\domain\value_objects;

/** Mismo prefijo que el desplegable de Entrada (`@plantilla:id`). */
final class ReferenciaPlantillaEnConcepto
{
    private const PREFIJO = '@plantilla:';

    public static function codigo(int $plantillaId): string
    {
        return self::PREFIJO . $plantillaId;
    }

    public static function idDesdeCodigo(string $codigo): ?int
    {
        if (!str_starts_with($codigo, self::PREFIJO)) {
            return null;
        }
        $id = (int) substr($codigo, strlen(self::PREFIJO));

        return $id > 0 ? $id : null;
    }
}
