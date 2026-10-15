<?php

declare(strict_types=1);

namespace src\cierre\application;

use DateTimeImmutable;
use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\application\ListarApuntes;
use src\asientos\domain\contracts\AsientoRepository;
use src\cierre\domain\services\RepartoCierre;
use src\cierre\domain\services\SuperavitVivienda;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

/**
 * Cuota acumulada de vivienda y lo ingresado aparte del cierre automático.
 * La cuota no está guardada: se recalcula como en el cierre de mes.
 */
final class MedirSuperavitVivienda
{
    public function __construct(
        private readonly ConfiguracionRepository $config,
        private readonly PersonaRepository $personas,
        private readonly AsientoRepository $asientos,
        private readonly ListarApuntes $listarApuntes,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /**
     * @return array<string, array{ingresado: Dinero, objetivo: Dinero, superavit: Dinero}>
     *   clave: iniciales en minúsculas
     */
    public function ejecutar(?DateTimeImmutable $hasta = null): array
    {
        $cfg = $this->config->get();
        $fechaHasta = $hasta ?? $cfg->fechaCierre;
        $contexto = $this->ambito->ejecutar();
        $personas = $this->personas->listarDeCentro($contexto->centroId);
        $conceptoG = $cfg->tipoCierre === 'necesidades' ? '14' : '11';
        $filas = $this->listarApuntes->ejecutar([
            'desde' => $cfg->fechaInicio->format('Y-m-d'),
            'hasta' => $fechaHasta->format('Y-m-d'),
            'cuenta' => 'G',
            'concepto' => $conceptoG,
            'origen' => 'A',
        ]);

        $snaps = $this->meses($cfg, $contexto->centroId, $contexto->ejercicioId, $fechaHasta, $filas);
        $objetivo = RepartoCierre::objetivoAcumulado($personas, $snaps);
        $ingresado = $this->ingresadoVoluntario($filas);

        $out = [];
        foreach ($personas as $p) {
            $ini = strtolower(trim($p->iniciales));
            if ($ini === '') {
                continue;
            }
            $pagado = $ingresado[$ini] ?? Dinero::zero();
            $cuota = $objetivo[$ini] ?? Dinero::zero();
            $out[$ini] = [
                'ingresado' => $pagado,
                'objetivo' => $cuota,
                'superavit' => Dinero::fromCents(SuperavitVivienda::cents($pagado->toCents(), $cuota->toCents())),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return list<array{mes: int, gastos: Dinero, aportaciones: array<string, Dinero>}>
     */
    private function meses(
        ConfiguracionCentro $cfg,
        int $centroId,
        int $ejercicioId,
        DateTimeImmutable $hasta,
        array $filas,
    ): array {
        $porMes = [];
        foreach ($filas as $fila) {
            $ini = strtolower(trim((string) ($fila['iniciales'] ?? '')));
            $fecha = (string) ($fila['fecha'] ?? '');
            if ($ini === '' || strlen($fecha) < 7) {
                continue;
            }
            $ym = substr($fecha, 0, 7);
            $cents = Dinero::fromInput((string) ($fila['cantidad'] ?? '0'))->toCents();
            $porMes[$ym][$ini] = ($porMes[$ym][$ini] ?? 0) + $cents;
        }

        $cursor = $cfg->fechaInicio->modify('first day of this month')->setTime(0, 0);
        $limite = $hasta->modify('first day of this month')->setTime(0, 0);
        $snaps = [];
        while ($cursor <= $limite) {
            $mesHasta = $cursor->modify('last day of this month');
            if ($mesHasta > $hasta) {
                $mesHasta = $hasta;
            }
            $ym = $cursor->format('Y-m');
            $gastos = $this->gastosGenerales($centroId, $ejercicioId, $cursor, $mesHasta);
            $aportaciones = [];
            foreach ($porMes[$ym] ?? [] as $ini => $cents) {
                $aportaciones[$ini] = Dinero::fromCents($cents);
            }
            $snaps[] = [
                'mes' => (int) $cursor->format('n'),
                'gastos' => $gastos,
                'aportaciones' => $aportaciones,
            ];
            $cursor = $cursor->modify('first day of next month');
        }

        return $snaps;
    }

    private function gastosGenerales(
        int $centroId,
        int $ejercicioId,
        DateTimeImmutable $desde,
        DateTimeImmutable $hasta,
    ): Dinero {
        $realizado = $this->asientos->realizadoPorConcepto(
            $centroId,
            $ejercicioId,
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

    /**
     * G/11 (o G/14) que no es el cierre automático: lo que la persona ha ingresado.
     *
     * @param list<array<string, mixed>> $filas
     * @return array<string, Dinero>
     */
    private function ingresadoVoluntario(array $filas): array
    {
        $cents = [];
        foreach ($filas as $fila) {
            if (!empty($fila['es_cierre'])) {
                continue;
            }
            $ini = strtolower(trim((string) ($fila['iniciales'] ?? '')));
            if ($ini === '') {
                continue;
            }
            $cents[$ini] = ($cents[$ini] ?? 0) + Dinero::fromInput((string) ($fila['cantidad'] ?? '0'))->toCents();
        }
        $out = [];
        foreach ($cents as $ini => $total) {
            $out[$ini] = Dinero::fromCents($total);
        }

        return $out;
    }
}
