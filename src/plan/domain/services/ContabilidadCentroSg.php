<?php

declare(strict_types=1);

namespace src\plan\domain\services;

/**
 * Contabilidad del plan H16s (centro sg): un solo libro G; 41–54 son gastos/destinos.
 * No existen los traspasos caja↔banco del H16n (conceptos puente 41/42).
 */
final class ContabilidadCentroSg
{
    public static function admiteTraspasoCajaBanco(string $planContableCodigo): bool
    {
        return !CatalogoPlanesContables::esCentroSg($planContableCodigo);
    }

    public static function esCodigoDestino(string $codigo): bool
    {
        return preg_match('/^(41|4[2-9]|5[0-4])$/', $codigo) === 1;
    }
}
