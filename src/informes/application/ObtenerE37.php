<?php

declare(strict_types=1);

namespace src\informes\application;

use src\ambito\application\ResolverAmbitoActual;
use src\cierre\application\MedirSuperavitVivienda;
use src\cierre\domain\services\CuentasDeudaVivienda;
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
        private readonly MedirSuperavitVivienda $superavit,
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

        $saldosCc = $this->saldosPorMaestro($contexto->centroId, $contexto->ejercicioId, $desde, $hasta, '9');
        $deudas = $this->saldosPorMaestro($contexto->centroId, $contexto->ejercicioId, $desde, $hasta, CuentasDeudaVivienda::MAESTRO);
        $superavits = $this->superavitPorIniciales();

        $filas = [];
        foreach ($this->personas->listarDeCentro($contexto->centroId) as $p) {
            $ini = strtolower(trim($p->iniciales));
            $tot = CalculadoraE37::totalesDesdeMovimientos(
                $movs[$p->iniciales] ?? [],
                $saldosCc[$p->iniciales] ?? 0,
                $deudas[$p->iniciales] ?? 0,
                $superavits[$ini] ?? 0,
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
        $avisoDeuda = null;
        $avisoSuperavit = null;
        foreach ($filas as $fila) {
            if ($aviso === null && Dinero::fromInput((string) ($fila['saldo_cc'] ?? '0'))->isNegative()) {
                $aviso = CalculadoraE37::avisoSaldoCcNegativo();
            }
            if ($avisoDeuda === null && Dinero::fromInput((string) ($fila['deuda_viv'] ?? '0'))->isNegative()) {
                $avisoDeuda = CalculadoraE37::avisoDeudaVivienda();
            }
            if ($avisoSuperavit === null && Dinero::fromInput((string) ($fila['superavit_viv'] ?? '0'))->toCents() > 0) {
                $avisoSuperavit = CalculadoraE37::avisoSuperavitVivienda();
            }
        }

        return [
            'config' => $cfg->toArray(),
            'filas' => $filas,
            'aviso_saldo_cc' => $aviso,
            'aviso_deuda' => $avisoDeuda,
            'aviso_superavit' => $avisoSuperavit,
        ];
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
            'por_fecha_imputacion' => true,
        ];
        if ($iniciales) {
            $filtros['iniciales'] = $iniciales;
        }
        $apuntes = self::apuntesConFechaImputacion($this->listarApuntes->ejecutar($filtros));

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
            $saldosCc = $this->saldosPorMaestro($contexto->centroId, $contexto->ejercicioId, $desde, $hasta, '9');
            $deudas = $this->saldosPorMaestro($contexto->centroId, $contexto->ejercicioId, $desde, $hasta, CuentasDeudaVivienda::MAESTRO);
            $superavits = $this->superavitPorIniciales();
            $tot = CalculadoraE37::totalesDesdeMovimientos(
                $movs[$iniciales] ?? [],
                $saldosCc[$iniciales] ?? 0,
                $deudas[$iniciales] ?? 0,
                $superavits[strtolower(trim($iniciales))] ?? 0,
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
            $avisoDeuda = null;
            if (Dinero::fromInput((string) ($totArr['deuda_viv'] ?? '0'))->isNegative()) {
                $avisoDeuda = CalculadoraE37::avisoDeudaVivienda();
            }
            $avisoSuperavit = null;
            if (Dinero::fromInput((string) ($totArr['superavit_viv'] ?? '0'))->toCents() > 0) {
                $avisoSuperavit = CalculadoraE37::avisoSuperavitVivienda();
            }
            $out = [
                'config' => $cfg->toArray(),
                'iniciales' => $iniciales,
                'apuntes' => $apuntes,
                'totales' => $totArr,
                'aviso_saldo_cc' => $aviso,
                'aviso_deuda' => $avisoDeuda,
                'aviso_superavit' => $avisoSuperavit,
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
    private static function apuntesConFechaImputacion(array $apuntes): array
    {
        return array_map(static function (array $a): array {
            if (!empty($a['fecha_imputacion'])) {
                $a['fecha'] = $a['fecha_imputacion'];
                $a['fecha_es'] = $a['fecha_imputacion_es'] ?? $a['fecha_es'];
            }

            return $a;
        }, $apuntes);
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
            $fechaImputacion = (string) ($a['fecha_imputacion'] ?? $a['fecha'] ?? '');
            $filas[] = [
                'fecha' => $fechaImputacion,
                'fecha_es' => (string) ($a['fecha_imputacion_es'] ?? $a['fecha_es'] ?? ''),
                'concepto' => (string) ($a['observaciones'] ?? ''),
                'celdas' => $celdas,
            ];
        }

        return $filas;
    }

    /** @return array<string, int> iniciales en minúsculas => céntimos de superávit */
    private function superavitPorIniciales(): array
    {
        $out = [];
        foreach ($this->superavit->ejecutar() as $ini => $row) {
            $out[$ini] = $row['superavit']->toCents();
        }

        return $out;
    }

    /** @return array<string, int> iniciales => cents */
    private function saldosPorMaestro(
        int $centroId,
        int $ejercicioId,
        string $desde,
        string $hasta,
        string $maestro,
    ): array {
        $personasPorId = [];
        foreach ($this->personas->listarDeCentro($centroId) as $p) {
            if ($p->id !== null) {
                $personasPorId[$p->id] = $p;
            }
        }

        $saldos = $this->asientos->saldosPorCuenta($centroId, $ejercicioId, $desde, $hasta, 'P', true);
        $out = [];
        foreach ($saldos as $row) {
            if (
                $row['tipo'] !== 'personal'
                || $row['persona_id'] === null
                || $row['codigo_maestro'] !== $maestro
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
