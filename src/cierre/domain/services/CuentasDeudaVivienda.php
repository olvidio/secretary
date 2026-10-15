<?php

declare(strict_types=1);

namespace src\cierre\domain\services;

/** Cuentas de la deuda de vivienda imputada en el cierre, fuera del saldo contable (CC / 9). */
final class CuentasDeudaVivienda
{
    public const MAESTRO = '91';

    public const PREFIJO = 'DEUDA.VIV.';

    public const REG = 'REG.DEUDA.VIV';

    public const MAESTRO_REG = 'REG-DEUDA';

    /** Exceso de vivienda ya ingresado, contrapartida al saldar sin tocar el saldo contable. */
    public const SUPERAVIT = 'SUPERAVIT.VIV';

    public const PREFIJO_SUPERAVIT = 'SUPERAVIT.VIV.';

    public const MAESTRO_SUPERAVIT = '92';

    public const MAESTRO_SUPERAVIT_G = 'SUPERAVIT-VIV';

    public const TIPO_SUPERAVIT = 'superavit_vivienda';

    /** @var list<string> */
    public const CONCEPTOS_P = ['211', '6'];

    public static function esConceptoCierre(string $codigo): bool
    {
        return in_array($codigo, self::CONCEPTOS_P, true);
    }

    public static function codigoPersona(string $iniciales): string
    {
        return self::PREFIJO . strtoupper($iniciales);
    }

    public static function codigoSuperavit(string $iniciales): string
    {
        return self::PREFIJO_SUPERAVIT . strtoupper($iniciales);
    }
}
