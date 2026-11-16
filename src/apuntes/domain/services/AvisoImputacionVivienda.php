<?php

declare(strict_types=1);

namespace src\apuntes\domain\services;

/**
 * Al anotar vivienda con fecha de imputación distinta: la otra mitad (P/211 ↔
 * G/11, P/6 ↔ G/14) tiene que contarse el mismo día, o Comprobaciones y los
 * 613 P y G la verán en meses distintos.
 */
final class AvisoImputacionVivienda
{
    /** @var array<string, array{0: string, 1: string}> cuenta|concepto => [otra mitad, qué es] */
    private const PAREJAS = [
        'P|211' => ['G/11', 'vivienda'],
        'G|11' => ['P/211', 'vivienda'],
        'P|6' => ['G/14', 'necesidades de la casa'],
        'G|14' => ['P/6', 'necesidades de la casa'],
    ];

    /**
     * @param bool $conContrapartidas la entrada genera ya las dos mitades (con la misma imputación)
     * @return list<string>
     */
    public static function avisos(
        string $cuenta,
        string $concepto,
        string $fechaOperacion,
        string $fechaImputacion,
        bool $conContrapartidas,
    ): array {
        $fechaImputacion = trim($fechaImputacion);
        if ($conContrapartidas || $fechaImputacion === '' || $fechaImputacion === trim($fechaOperacion)) {
            return [];
        }
        $pareja = self::PAREJAS[strtoupper(trim($cuenta)) . '|' . trim($concepto)] ?? null;
        if ($pareja === null) {
            return [];
        }
        $imp = \DateTimeImmutable::createFromFormat('!Y-m-d', $fechaImputacion);
        $impEs = $imp !== false ? $imp->format('d/m/Y') : $fechaImputacion;

        return [sprintf(
            _('Este apunte de %s se cuenta el %s. El %s que lo acompaña tiene que llevar la misma fecha de imputación; si no, no cuadran en Comprobaciones ni en el 613 de ese mes.'),
            $pareja[1],
            $impEs,
            $pareja[0],
        )];
    }
}
