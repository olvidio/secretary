<?php

declare(strict_types=1);

namespace src\listados\application;

use InvalidArgumentException;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\asientos\domain\contracts\AsientoRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\plan\domain\contracts\DestinoSgRepository;
use src\plan\domain\services\CatalogoPlanesContables;
use src\shared\domain\value_objects\Dinero;

final class ObtenerDonativosFundacionSg
{
    /** Opción del desplegable que no es un destino: ingresos cobrados en metálico. */
    public const INGRESOS_CAJA = 'caja';

    public function __construct(
        private readonly ResolverAmbitoActual $ambito,
        private readonly CentroRepository $centros,
        private readonly ConfiguracionRepository $config,
        private readonly DestinoSgRepository $destinos,
        private readonly PersonaRepository $personas,
        private readonly AsientoRepository $asientos,
    ) {
    }

    /**
     * @return array{
     *   fundaciones: list<array{codigo:string,etiqueta:string}>,
     *   periodo: array{desde:string,hasta:string},
     *   destino: ?array{codigo:string,etiqueta:string},
     *   filas: list<array{persona_id:int,nombre:string,iniciales:string,acumulado_es:string}>,
     *   total_es: string
     * }
     */
    public function ejecutar(?string $conceptoCodigo): array
    {
        $ctx = $this->ambito->ejecutar();
        $centro = $this->centros->porId($ctx->centroId);
        if ($centro === null || !CatalogoPlanesContables::esCentroSg($centro->planContableCodigo)) {
            throw new InvalidArgumentException(_('Este informe es del plan H16s'));
        }
        $fundaciones = $this->fundaciones($ctx->centroId);
        $periodoCfg = $this->config->get()->periodo();
        $periodo = [
            'desde' => $periodoCfg->fechaInicio->format('Y-m-d'),
            'hasta' => $periodoCfg->fechaCorte->format('Y-m-d'),
        ];
        $out = [
            'fundaciones' => $fundaciones,
            'periodo' => $periodo,
            'destino' => null,
            'filas' => [],
            'total_es' => '',
        ];
        $conceptoCodigo = trim((string) ($conceptoCodigo ?? ''));
        if ($conceptoCodigo === '') {
            return $out;
        }
        if ($conceptoCodigo === self::INGRESOS_CAJA) {
            return $this->ingresosEnCaja($out, $ctx->centroId, $ctx->ejercicioId);
        }
        $etiquetaDestino = null;
        foreach ($fundaciones as $f) {
            if ($f['codigo'] === $conceptoCodigo) {
                $etiquetaDestino = $f['etiqueta'];
                break;
            }
        }
        if ($etiquetaDestino === null) {
            throw new InvalidArgumentException(_('Destino no válido'));
        }
        $out['destino'] = ['codigo' => $conceptoCodigo, 'etiqueta' => $etiquetaDestino];
        $desde = $periodo['desde'];
        $hasta = $periodo['hasta'];
        $porPersona = $this->asientos->realizadoPorConceptoYPersona(
            $ctx->centroId,
            $ctx->ejercicioId,
            'G',
            $desde,
            $hasta,
        );
        $totalCents = 0;
        foreach ($this->personas->listarDeCentro($ctx->centroId) as $persona) {
            if ($persona->id === null) {
                continue;
            }
            $cents = (int) ($porPersona[$persona->id][$conceptoCodigo] ?? 0);
            if ($cents === 0) {
                continue;
            }
            $totalCents += $cents;
            $out['filas'][] = [
                'persona_id' => $persona->id,
                'nombre' => trim($persona->apellidos . ', ' . $persona->nombre, ' ,'),
                'iniciales' => $persona->iniciales,
                'acumulado_es' => Dinero::fromCents($cents)->formatEs(),
            ];
        }
        // Lo que salió al destino sin persona (o con una que ya no está en Nombres): una fila
        // aparte, para que el total sea todo lo que ha salido a ese destino.
        $totalDestino = (int) ($this->asientos->realizadoPorConcepto(
            $ctx->centroId,
            $ctx->ejercicioId,
            'G',
            $desde,
            $hasta,
        )[$conceptoCodigo] ?? 0);
        $sinNombre = $totalDestino - $totalCents;
        if ($sinNombre !== 0) {
            $totalCents += $sinNombre;
            $out['filas'][] = [
                'persona_id' => 0,
                'nombre' => _('Sin nombre'),
                'iniciales' => '',
                'acumulado_es' => Dinero::fromCents($sinNombre)->formatEs(),
            ];
        }
        if ($totalCents !== 0) {
            $out['total_es'] = Dinero::fromCents($totalCents)->formatEs();
        }

        return $out;
    }

    /**
     * Ingresos de cada persona que no han salido a ningún destino: lo ingresado (conceptos
     * de ingreso) menos lo que, a su nombre, ha ido del 41 en adelante, que ya sale en el
     * listado de ese destino. Solo salen las personas a las que les queda algo.
     *
     * @param array<string, mixed> $out
     * @return array<string, mixed>
     */
    private function ingresosEnCaja(array $out, int $centroId, int $ejercicioId): array
    {
        $out['destino'] = ['codigo' => self::INGRESOS_CAJA, 'etiqueta' => _('Ingresos en caja')];
        $desde = $out['periodo']['desde'];
        $hasta = $out['periodo']['hasta'];
        $ingresos = $this->asientos->ingresosPorPersona($centroId, $ejercicioId, 'G', $desde, $hasta);
        $destinos = [];
        foreach ($this->asientos->realizadoPorConceptoYPersona($centroId, $ejercicioId, 'G', $desde, $hasta) as $pid => $porConcepto) {
            foreach ($porConcepto as $codigo => $cents) {
                if (ctype_digit((string) $codigo) && (int) $codigo >= 41) {
                    $destinos[$pid] = ($destinos[$pid] ?? 0) + (int) $cents;
                }
            }
        }
        $totalCents = 0;
        foreach ($this->personas->listarDeCentro($centroId) as $persona) {
            if ($persona->id === null) {
                continue;
            }
            $cents = (int) ($ingresos[$persona->id] ?? 0) - (int) ($destinos[$persona->id] ?? 0);
            unset($ingresos[$persona->id], $destinos[$persona->id]);
            if ($cents === 0) {
                continue;
            }
            $totalCents += $cents;
            $out['filas'][] = [
                'persona_id' => $persona->id,
                'nombre' => trim($persona->apellidos . ', ' . $persona->nombre, ' ,'),
                'iniciales' => $persona->iniciales,
                'acumulado_es' => Dinero::fromCents($cents)->formatEs(),
            ];
        }
        // Lo que no lleva persona, o lleva una que ya no está en Nombres, va en una sola fila.
        $sinNombre = array_sum($ingresos) - array_sum($destinos);
        if ($sinNombre !== 0) {
            $totalCents += $sinNombre;
            $out['filas'][] = [
                'persona_id' => 0,
                'nombre' => _('Sin nombre'),
                'iniciales' => '',
                'acumulado_es' => Dinero::fromCents($sinNombre)->formatEs(),
            ];
        }
        if ($out['filas'] !== []) {
            $out['total_es'] = Dinero::fromCents($totalCents)->formatEs();
        }

        return $out;
    }

    /** @return list<array{codigo:string,etiqueta:string}> */
    private function fundaciones(int $centroId): array
    {
        if ($this->destinos->nombrados($centroId) === null) {
            throw new InvalidArgumentException(_('Este informe es del plan H16s'));
        }
        $out = [
            ['codigo' => self::INGRESOS_CAJA, 'etiqueta' => _('Ingresos en caja')],
            [
                'codigo' => '41',
                'etiqueta' => '41 ' . _('Necesidades generales'),
            ],
        ];
        foreach ($this->destinos->paraCentro($centroId) as $partida) {
            $codigo = (string) $partida['codigo'];
            if ((int) $codigo < 42) {
                continue;
            }
            $out[] = [
                'codigo' => $codigo,
                'etiqueta' => (string) $partida['etiqueta'],
            ];
        }

        return $out;
    }
}
