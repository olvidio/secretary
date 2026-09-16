<?php

declare(strict_types=1);

namespace src\informes\application;

use src\ambito\application\ResolverAmbitoActual;
use src\apuntes\application\ListarApuntes;
use src\asientos\domain\contracts\AsientoRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\informes\domain\services\CalculadoraE37;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\value_objects\Dinero;

final class ObtenerE37
{
    public function __construct(
        private readonly ConfiguracionRepository $config,
        private readonly PersonaRepository $personas,
        private readonly AsientoRepository $asientos,
        private readonly ListarApuntes $listarApuntes,
        private readonly ResolverAmbitoActual $ambito,
    ) {
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        $cfg = $this->config->get();
        $contexto = $this->ambito->ejecutar();
        $desde = $cfg->fechaInicio->format('Y-m-d');
        $hasta = $cfg->fechaCierre->format('Y-m-d');

        $movs = $this->asientos->movimientosE37PorPersona(
            $contexto->centroId,
            $contexto->ejercicioId,
            $desde,
            $hasta,
        );

        $saldosCc = $this->saldosCcPorPersona($contexto->centroId, $contexto->ejercicioId, $desde, $hasta);

        $filas = [];
        foreach ($this->personas->listarDeCentro($contexto->centroId) as $p) {
            $tot = CalculadoraE37::totalesDesdeMovimientos(
                $movs[$p->iniciales] ?? [],
                $saldosCc[$p->iniciales] ?? 0,
            );
            $fila = [
                'iniciales' => $p->iniciales,
                'nombre' => $p->nombreCompleto(),
            ];
            foreach ($tot as $k => $d) {
                $fila[$k] = $d->toString();
                $fila[$k . '_es'] = $d->formatEs();
            }
            $filas[] = $fila;
        }

        $aviso = null;
        foreach ($filas as $fila) {
            if (Dinero::fromInput((string) ($fila['saldo_cc'] ?? '0'))->isNegative()) {
                $aviso = CalculadoraE37::avisoSaldoCcNegativo();
                break;
            }
        }

        return ['config' => $cfg->toArray(), 'filas' => $filas, 'aviso_saldo_cc' => $aviso];
    }

    /** @return array<string, mixed> */
    public function detalle(?string $iniciales): array
    {
        $cfg = $this->config->get();
        $contexto = $this->ambito->ejecutar();
        $filtros = [
            'cuenta' => 'P',
            'desde' => $cfg->fechaInicio->format('Y-m-d'),
            'hasta' => $cfg->fechaCierre->format('Y-m-d'),
        ];
        if ($iniciales) {
            $filtros['iniciales'] = $iniciales;
        }
        $apuntes = $this->listarApuntes->ejecutar($filtros);

        $totArr = null;
        if ($iniciales) {
            $desde = $cfg->fechaInicio->format('Y-m-d');
            $hasta = $cfg->fechaCierre->format('Y-m-d');
            $movs = $this->asientos->movimientosE37PorPersona(
                $contexto->centroId,
                $contexto->ejercicioId,
                $desde,
                $hasta,
            );
            $saldosCc = $this->saldosCcPorPersona($contexto->centroId, $contexto->ejercicioId, $desde, $hasta);
            $tot = CalculadoraE37::totalesDesdeMovimientos(
                $movs[$iniciales] ?? [],
                $saldosCc[$iniciales] ?? 0,
            );
            $totArr = [];
            foreach ($tot as $k => $d) {
                $totArr[$k] = $d->toString();
                $totArr[$k . '_es'] = $d->formatEs();
            }

            $p = $this->personas->porInicialesDeCentro($contexto->centroId, $iniciales);
            $aviso = null;
            if (Dinero::fromInput((string) ($totArr['saldo_cc'] ?? '0'))->isNegative()) {
                $aviso = CalculadoraE37::avisoSaldoCcNegativo();
            }
            $out = [
                'config' => $cfg->toArray(),
                'iniciales' => $iniciales,
                'apuntes' => $apuntes,
                'totales' => $totArr,
                'aviso_saldo_cc' => $aviso,
                'persona' => $p !== null
                    ? ['iniciales' => $p->iniciales, 'nombre' => $p->nombreCompleto()]
                    : ['iniciales' => $iniciales, 'nombre' => $iniciales],
                'columnas' => CalculadoraE37::columnasHoja(),
                'filas' => self::filasHoja($apuntes),
            ];

            return $out;
        }

        return [
            'config' => $cfg->toArray(),
            'iniciales' => $iniciales,
            'apuntes' => $apuntes,
            'totales' => $totArr,
        ];
    }

    /**
     * @param list<array<string, mixed>> $apuntes
     * @return list<array<string, mixed>>
     */
    private static function filasHoja(array $apuntes): array
    {
        $filas = [];
        foreach ($apuntes as $a) {
            $importe = new Dinero((string) $a['cantidad']);
            $celdas = [];
            foreach (CalculadoraE37::celdasDeMovimiento((string) $a['concepto_codigo'], $importe) as $k => $d) {
                $celdas[$k] = $d->toString();
                $celdas[$k . '_es'] = $d->formatEs();
            }
            $filas[] = [
                'fecha' => $a['fecha'] ?? '',
                'concepto' => (string) ($a['observaciones'] ?? ''),
                'celdas' => $celdas,
            ];
        }

        return $filas;
    }

    /** @return array<string, int> iniciales => saldo_cc cents */
    private function saldosCcPorPersona(int $centroId, int $ejercicioId, string $desde, string $hasta): array
    {
        $personasPorId = [];
        foreach ($this->personas->listarDeCentro($centroId) as $p) {
            if ($p->id !== null) {
                $personasPorId[$p->id] = $p;
            }
        }

        $saldos = $this->asientos->saldosPorCuenta($centroId, $ejercicioId, $desde, $hasta, 'P');
        $out = [];
        foreach ($saldos as $row) {
            if (
                $row['tipo'] !== 'personal'
                || $row['persona_id'] === null
                || $row['codigo_maestro'] !== '9'
            ) {
                continue;
            }
            $persona = $personasPorId[$row['persona_id']] ?? null;
            if ($persona !== null) {
                $out[$persona->iniciales] = $row['saldo_cents'];
            }
        }

        return $out;
    }
}
