<?php

declare(strict_types=1);

namespace src\informes\domain\services;

/**
 * Antes de ver o imprimir el 613: de las comprobaciones, las que fallan, con
 * qué hay que hacer y a qué pantalla ir (`destino`: saldos, comprobaciones,
 * cierre o apuntes; la vista pone el enlace).
 */
final class AvisosAntes613
{
    /**
     * @param list<array<string, mixed>> $comprobaciones cada una con id, titulo, ok, mensaje
     * @return array{ok: bool, avisos: list<array{id: string, titulo: string, mensaje: string, que_hacer: string, destino: string}>}
     */
    public static function armar(array $comprobaciones): array
    {
        $avisos = [];
        foreach ($comprobaciones as $c) {
            if (($c['ok'] ?? true) === true) {
                continue;
            }
            $id = (string) ($c['id'] ?? '');
            [$destino, $queHacer] = self::guia($id);
            $avisos[] = [
                'id' => $id,
                'titulo' => (string) ($c['titulo'] ?? $id),
                'mensaje' => (string) ($c['mensaje'] ?? ''),
                'que_hacer' => $queHacer,
                'destino' => $destino,
            ];
        }

        return ['ok' => $avisos === [], 'avisos' => $avisos];
    }

    /** @return array{0: string, 1: string} */
    private static function guia(string $id): array
    {
        return match ($id) {
            'partida_doble' => ['apuntes', _('Hay que corregir el asiento descuadrado en Apuntes. Hasta entonces ninguna cifra del resumen es fiable.')],
            'saldo_a_global', 'apuntes_a' => ['saldos', _('En Saldos, pulsar «Ejecutar comprobaciones»: sale quién tiene la cuenta personal descuadrada, qué contrapartida le falta (normalmente un 111 o el gasto de vivienda) y un enlace a sus apuntes.')],
            'deuda_vivienda' => ['saldos', _('En Saldos, pulsar «Saldar» en cada persona con deuda de vivienda: aplica primero su superávit de vivienda y, si no alcanza, su saldo contable positivo. Si no aparece el botón o no alcanza, falta anotar lo que ha pagado de vivienda.')],
            'vivienda_21_11' => ['comprobaciones', _('En Comprobaciones, la tabla de vivienda enlaza los apuntes P/211 y G/11 de cada persona: falta uno de los dos o los importes no coinciden.')],
            'meses_sin_movimiento' => ['comprobaciones', _('En Comprobaciones salen las personas y los meses. Si esa persona no estaba, hay que marcarle la exención en Nombres; si estaba, faltan sus apuntes.')],
            'labores_duplicadas' => ['comprobaciones', _('En Comprobaciones, el bloque «Partidas 7 apuntadas dos veces» enseña cada caso con sus dos apuntes. Si es el mismo donativo, pulsar «Anular asignación»: el gasto queda una sola vez y la c/c de la persona recupera el importe.')],
            'cierre_meses_anteriores' => ['cierre', _('En Cierre de mes, pulsar «Regularizar meses anteriores».')],
            'cierre_mes_actual' => ['cierre', _('En Cierre de mes, revisar la tabla y pulsar «Generar apuntes». Se puede repetir: borra el cierre anterior del mes y lo vuelve a generar.')],
            default => ['comprobaciones', ''],
        };
    }
}
