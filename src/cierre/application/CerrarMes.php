<?php

declare(strict_types=1);

namespace src\cierre\application;

use DateTimeImmutable;
use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\value_objects\ContextoActual;
use src\apuntes\application\CrearApunte;
use src\apuntes\application\ListarApuntes;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use src\cierre\domain\services\MesesSinCierre;
use src\cierre\domain\services\RepartoCierre;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

final class CerrarMes
{
    public function __construct(
        private readonly ConfiguracionRepository $config,
        private readonly PersonaRepository $personas,
        private readonly AsientoRepository $asientos,
        private readonly CrearApunte $crearApunte,
        private readonly ListarApuntes $listarApuntes,
        private readonly ResolverAmbitoActual $ambito,
        private readonly MesesSinCierre $mesesSinCierre,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(bool $confirmar = true): array
    {
        $cfg = $this->config->get();
        $contexto = $this->ambito->ejecutar();
        $cierre = $cfg->fechaCierre;
        $mes = (int) $cierre->format('n');
        $desde = $cierre->modify('first day of this month');
        $hasta = $cierre->modify('last day of this month');

        $personas = $this->personasDeCentro($contexto->centroId);
        $preparado = $this->prepararReparto($cfg, $desde, $hasta, $contexto, $personas, $mes);
        $gastos = $preparado['gastos'];
        $reparto = $preparado['reparto'];
        if (!$confirmar) {
            return [
                'preview' => true,
                'mes' => $mes,
                'gastos_generales' => $gastos->toString(),
                'gastos_generales_es' => $gastos->formatEs(),
                'aportaciones_externas_es' => $preparado['aportaciones_externas']->formatEs(),
                'base_reparto_es' => $preparado['base_reparto']->formatEs(),
                'restante_es' => $preparado['restante']->formatEs(),
                'tipo_cierre' => $cfg->tipoCierre,
                'lineas' => $this->lineasPreview($reparto),
                'meses_faltantes' => $this->detectarMesesFaltantes($cfg, $contexto),
            ];
        }

        $creados = $this->generarParaRango($cfg, $contexto, $desde, $hasta, $reparto);

        return [
            'preview' => false,
            'creados' => count($creados),
            'gastos_generales' => $gastos->toString(),
            'apuntes' => $creados,
        ];
    }

    /**
     * Genera el cierre de los meses anteriores que faltan.
     *
     * @param list<string>|null $mesesYm Si se indica, solo esos Y-m (deben estar pendientes).
     * @return array<string, mixed>
     */
    public function regularizar(?array $mesesYm = null): array
    {
        $cfg = $this->config->get();
        $contexto = $this->ambito->ejecutar();
        $faltantes = $this->detectarMesesFaltantes($cfg, $contexto);
        if ($faltantes['ok']) {
            return [
                'creados' => 0,
                'meses' => [],
                'mensaje' => _("No hay meses anteriores pendientes de cierre."),
            ];
        }

        $pendientes = [];
        foreach ($faltantes['meses'] as $mes) {
            $pendientes[$mes['ym']] = $mes;
        }
        if ($mesesYm !== null) {
            $filtrados = [];
            foreach ($mesesYm as $ym) {
                if (!isset($pendientes[$ym])) {
                    throw new InvalidArgumentException(sprintf(_("El mes %s no está pendiente de cierre."), $ym));
                }
                $filtrados[$ym] = $pendientes[$ym];
            }
            $pendientes = $filtrados;
        }

        $personas = $this->personasDeCentro($contexto->centroId);
        $totalCreados = 0;
        $procesados = [];
        foreach ($pendientes as $ym => $info) {
            $desde = new DateTimeImmutable($ym . '-01');
            $hasta = $desde->modify('last day of this month');
            $reparto = $this->prepararReparto(
                $cfg,
                $desde,
                $hasta,
                $contexto,
                $personas,
                (int) $info['mes'],
            )['reparto'];
            $creados = $this->generarParaRango($cfg, $contexto, $desde, $hasta, $reparto);
            $totalCreados += count($creados);
            $procesados[] = [
                'ym' => $ym,
                'mes_es' => $info['mes_es'],
                'creados' => count($creados),
            ];
        }

        return [
            'creados' => $totalCreados,
            'meses' => $procesados,
        ];
    }

    /**
     * @param list<array{persona: Persona, importe: Dinero}> $reparto
     * @return list<array<string, mixed>>
     */
    private function generarParaRango(
        ConfiguracionCentro $cfg,
        ContextoActual $contexto,
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        array $reparto,
    ): array {
        $this->asientos->borrarCierresEntre($contexto->ejercicioId, $desde, $hasta);
        $conceptoP = $cfg->tipoCierre === 'necesidades' ? '6' : '21';
        $conceptoG = $cfg->tipoCierre === 'necesidades' ? '14' : '11';
        $obs = 'automático';
        $creados = [];
        foreach ($reparto as $r) {
            if ($r['importe']->isZero()) {
                continue;
            }
            $base = [
                'fecha' => $hasta->format('Y-m-d'),
                'origen' => 'A',
                'iniciales' => $r['persona']->iniciales,
                'observaciones' => $obs,
                'cantidad' => $r['importe']->toString(),
                'es_cierre' => true,
            ];
            foreach ($this->crearApunte->ejecutar($base + ['cuenta' => 'P', 'concepto_codigo' => $conceptoP]) as $a) {
                $creados[] = $a->toArray();
            }
            foreach ($this->crearApunte->ejecutar($base + ['cuenta' => 'G', 'concepto_codigo' => $conceptoG]) as $a) {
                $creados[] = $a->toArray();
            }
        }

        return $creados;
    }

    /** @return array{ok: bool, meses: list<array{ym: string, mes: int, mes_es: string, gastos_es: string}>} */
    private function detectarMesesFaltantes(ConfiguracionCentro $cfg, ContextoActual $contexto): array
    {
        $mesesRevisar = MesesSinCierre::mesesAnterioresAlCierre($cfg->fechaInicio, $cfg->fechaCierre);
        if ($mesesRevisar === []) {
            return ['ok' => true, 'meses' => []];
        }

        $personas = $this->personasDeCentro($contexto->centroId);
        $mesesConCierre = $this->mesesConAsientoCierre($contexto, $cfg->fechaInicio, $cfg->fechaCierre);
        $requieren = [];
        $gastosEs = [];
        foreach ($mesesRevisar as $ym) {
            $desde = new DateTimeImmutable($ym . '-01');
            $hasta = $desde->modify('last day of this month');
            $mesNum = (int) substr($ym, 5, 2);
            $preparado = $this->prepararReparto($cfg, $desde, $hasta, $contexto, $personas, $mesNum);
            $gastosEs[$ym] = $preparado['gastos']->formatEs();
            $reparto = $preparado['reparto'];
            $requieren[$ym] = $this->repartoGeneraApuntes($reparto);
        }

        return $this->mesesSinCierre->ejecutar($mesesRevisar, $mesesConCierre, $requieren, $gastosEs);
    }

    /** @return array<string, true> */
    private function mesesConAsientoCierre(
        ContextoActual $contexto,
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
    ): array {
        $limite = $hasta->modify('first day of this month')->modify('-1 day');
        if ($limite < $desde) {
            return [];
        }

        $asientos = $this->asientos->listar($contexto->ejercicioId, [
            'tipo' => 'cierre',
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $limite->format('Y-m-d'),
        ]);

        return $this->indexarMeses($asientos);
    }

    /**
     * @param list<Asiento> $asientos
     * @return array<string, true>
     */
    private function indexarMeses(array $asientos): array
    {
        $out = [];
        foreach ($asientos as $asiento) {
            $out[$asiento->fecha->format('Y-m')] = true;
        }

        return $out;
    }

    /**
     * @param list<array{persona: Persona, importe: Dinero}> $reparto
     */
    private function repartoGeneraApuntes(array $reparto): bool
    {
        foreach ($reparto as $r) {
            if (!$r['importe']->isZero()) {
                return true;
            }
        }

        return false;
    }

    private function gastosGeneralesDelMes(
        ContextoActual $contexto,
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
    ): Dinero {
        $realizado = $this->asientos->realizadoPorConcepto(
            $contexto->centroId,
            $contexto->ejercicioId,
            'G',
            $desde->format('Y-m-d'),
            $hasta->format('Y-m-d'),
            ['cierre'],
        );

        $gastos = Dinero::zero();
        for ($cod = 201; $cod <= 215; ++$cod) {
            $key = (string) $cod;
            $cents = array_key_exists($key, $realizado) ? $realizado[$key] : 0;
            $gastos = $gastos->add(Dinero::fromCents($cents));
        }

        return $gastos;
    }

    /** @return list<Persona> */
    private function personasDeCentro(int $centroId): array
    {
        return $this->personas->listarDeCentro($centroId);
    }

    /**
     * @param list<Persona> $personas
     * @return array{
     *   gastos: Dinero,
     *   aportaciones_externas: Dinero,
     *   base_reparto: Dinero,
     *   restante: Dinero,
     *   reparto: list<array{persona: Persona, importe: Dinero, importe_bruto: Dinero, ya_imputado: Dinero}>
     * }
     */
    private function prepararReparto(
        ConfiguracionCentro $cfg,
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        ContextoActual $contexto,
        array $personas,
        int $mes,
    ): array {
        $gastos = $this->gastosGeneralesDelMes($contexto, $desde, $hasta);
        $aportacionesMes = $this->aportacionesGenerales($cfg, $desde, $hasta);
        $aportacionesYtd = $this->aportacionesGenerales(
            $cfg,
            $cfg->fechaInicio,
            $hasta,
            $desde,
            $hasta,
        );
        $objetivoYtd = RepartoCierre::objetivoAcumulado(
            $personas,
            $this->mesesParaObjetivo($cfg, $contexto, $desde, $gastos, $aportacionesMes),
        );
        $base = RepartoCierre::gastosNetosParaReparto($gastos, $personas, $mes, $aportacionesMes);
        $externos = $gastos->sub($base);
        if ($externos->isNegative()) {
            $externos = Dinero::zero();
        }

        $restante = RepartoCierre::restantePorCubrir($gastos, $aportacionesMes);

        return [
            'gastos' => $gastos,
            'aportaciones_externas' => $externos,
            'base_reparto' => $base,
            'restante' => $restante,
            'reparto' => RepartoCierre::calcular(
                $gastos,
                $personas,
                $mes,
                $aportacionesMes,
                $aportacionesYtd,
                $objetivoYtd,
            ),
        ];
    }

    /**
     * @return list<array{mes:int,gastos:Dinero,aportaciones:array<string,Dinero>}>
     */
    private function mesesParaObjetivo(
        ConfiguracionCentro $cfg,
        ContextoActual $contexto,
        DateTimeImmutable $desdeMes,
        Dinero $gastosMes,
        array $aportacionesMes,
    ): array {
        $cursor = $cfg->fechaInicio->modify('first day of this month')->setTime(0, 0);
        $limite = $desdeMes->modify('first day of this month')->setTime(0, 0);
        $snaps = [];
        while ($cursor <= $limite) {
            $mesDesde = $cursor;
            $mesHasta = $cursor->modify('last day of this month');
            $mesNum = (int) $cursor->format('n');
            if ($cursor->format('Y-m') === $limite->format('Y-m')) {
                $snaps[] = ['mes' => $mesNum, 'gastos' => $gastosMes, 'aportaciones' => $aportacionesMes];
            } else {
                $snaps[] = [
                    'mes' => $mesNum,
                    'gastos' => $this->gastosGeneralesDelMes($contexto, $mesDesde, $mesHasta),
                    'aportaciones' => $this->aportacionesGenerales($cfg, $mesDesde, $mesHasta),
                ];
            }
            $cursor = $cursor->modify('first day of next month');
        }

        return $snaps;
    }

    /**
     * G/11 (o G/14) origen A. Si se indica el rango de cierre excluido, los
     * automáticos de ese mes no cuentan (se van a regenerar); los de meses
     * anteriores sí, para el acumulado del ejercicio.
     *
     * @return array<string, Dinero>
     */
    private function aportacionesGenerales(
        ConfiguracionCentro $cfg,
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
        ?DateTimeImmutable $cierreExcluidoDesde = null,
        ?DateTimeImmutable $cierreExcluidoHasta = null,
    ): array {
        $conceptoG = $cfg->tipoCierre === 'necesidades' ? '14' : '11';
        $excluirDesde = $cierreExcluidoDesde?->format('Y-m-d');
        $excluirHasta = $cierreExcluidoHasta?->format('Y-m-d');
        $acumulado = [];
        foreach ($this->listarApuntes->ejecutar([
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
            'cuenta' => 'G',
            'concepto' => $conceptoG,
            'origen' => 'A',
        ]) as $fila) {
            if (!empty($fila['es_cierre'])) {
                $f = (string) ($fila['fecha'] ?? '');
                if ($excluirDesde === null || ($f >= $excluirDesde && $f <= $excluirHasta)) {
                    continue;
                }
            }
            $ini = strtolower(trim((string) ($fila['iniciales'] ?? '')));
            if ($ini === '') {
                continue;
            }
            $cents = Dinero::fromInput((string) ($fila['cantidad'] ?? '0'))->toCents();
            $acumulado[$ini] = ($acumulado[$ini] ?? 0) + $cents;
        }

        $out = [];
        foreach ($acumulado as $ini => $cents) {
            $out[$ini] = Dinero::fromCents($cents);
        }

        return $out;
    }

    /**
     * @param list<array{persona: Persona, importe: Dinero, importe_bruto: Dinero, ya_imputado: Dinero}> $reparto
     * @return list<array<string, mixed>>
     */
    private function lineasPreview(array $reparto): array
    {
        $prev = [];
        foreach ($reparto as $r) {
            $prev[] = [
                'iniciales' => $r['persona']->iniciales,
                'nombre' => $r['persona']->nombreCompleto(),
                'importe' => $r['importe']->toString(),
                'importe_es' => $r['importe']->formatEs(),
                'importe_bruto_es' => $r['importe_bruto']->formatEs(),
                'ya_imputado_es' => $r['ya_imputado']->formatEs(),
            ];
        }

        return $prev;
    }
}
