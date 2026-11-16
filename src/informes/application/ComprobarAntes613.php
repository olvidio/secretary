<?php

declare(strict_types=1);

namespace src\informes\application;

use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\cierre\application\CerrarMes;
use src\informes\domain\services\AvisosAntes613;
use src\plan\domain\services\CatalogoPlanesContables;

/**
 * Lo que conviene revisar antes de ver o imprimir el 613 P o G de un centro n:
 * comprobaciones de Saldos y de Comprobaciones, cierre de vivienda y deuda.
 * El centro sg y el club no tienen libro personal ni cierre: no aplica.
 */
final class ComprobarAntes613
{
    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly ComprobarSaldos $saldos,
        private readonly ComprobarPersonalesGenerales $personalesGenerales,
        private readonly CerrarMes $cerrarMes,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(): array
    {
        $contexto = $this->ambito->ejecutar();
        $centro = $this->centros->porId($contexto->centroId);
        $plan = $centro->planContableCodigo ?? CatalogoPlanesContables::H16N;
        if (CatalogoPlanesContables::esCentroSg($plan) || CatalogoPlanesContables::esClub($plan)) {
            return ['aplica' => false, 'ok' => true, 'avisos' => []];
        }

        $saldos = $this->saldos->ejecutar(null);
        $pg = $this->personalesGenerales->ejecutar(null);
        $comprobaciones = array_merge(
            $saldos['comprobaciones'],
            $pg['comprobaciones'],
            $this->comprobacionesCierre(),
        );

        return ['aplica' => true, 'hasta' => $saldos['hasta']] + AvisosAntes613::armar($comprobaciones);
    }

    /** @return list<array<string, mixed>> */
    private function comprobacionesCierre(): array
    {
        $p = $this->cerrarMes->pendientes();
        $out = [];

        $meses = array_map(static fn (array $m): string => $m['mes_es'], $p['meses_anteriores']);
        $out[] = [
            'id' => 'cierre_meses_anteriores',
            'titulo' => _('Cierre de vivienda de meses anteriores'),
            'ok' => $meses === [],
            'mensaje' => $meses === []
                ? _('Todos los meses anteriores tienen su cierre.')
                : sprintf(_('Falta el cierre de vivienda de: %s.'), implode(', ', $meses)),
        ];

        $actual = $p['mes_actual'];
        $mensaje = match ($actual['estado']) {
            'sin_generar' => _('No se ha generado el cierre de vivienda del mes de la fecha de cierre.'),
            'desactualizado' => sprintf(
                _('El cierre de vivienda del mes ya no coincide con lo que saldría ahora (se han anotado gastos o pagos después de generarlo): %s.'),
                implode('; ', array_map(
                    static fn (array $d): string => sprintf(_('%s generado %s €, debería %s €'), $d['iniciales'], $d['generado_es'], $d['deberia_es']),
                    $actual['diferencias'],
                )),
            ),
            default => _('El cierre de vivienda del mes está al día.'),
        };
        $out[] = [
            'id' => 'cierre_mes_actual',
            'titulo' => _('Cierre de vivienda del mes'),
            'ok' => in_array($actual['estado'], ['ok', 'no_aplica'], true),
            'mensaje' => $mensaje,
        ];

        return $out;
    }
}
