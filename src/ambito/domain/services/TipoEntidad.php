<?php

declare(strict_types=1);

namespace src\ambito\domain\services;

/**
 * Tipo de la fila `centros`: una entidad contable, o el libro personal (`p`).
 * Las personas solo se vinculan a un centro n.
 */
final class TipoEntidad
{
    public const CENTRO_N = 'n';

    public const CENTRO_SG = 'sg';

    public const ASOCIACION = 'asociacion';

    public const FUNDACION = 'fundacion';

    /** Libro personal. No sale en la administración de entidades. */
    public const PERSONAL = 'p';

    /** @return list<string> */
    public static function deAlta(): array
    {
        return [self::CENTRO_N, self::CENTRO_SG, self::ASOCIACION, self::FUNDACION];
    }

    public static function esCentroParaPersona(string $tipo): bool
    {
        return $tipo === self::CENTRO_N;
    }

    public static function etiqueta(string $tipo): string
    {
        return match ($tipo) {
            self::CENTRO_N => _('centro n'),
            self::CENTRO_SG => _('centro sg'),
            self::ASOCIACION => _('asociación'),
            self::FUNDACION => _('fundación'),
            default => $tipo,
        };
    }
}
