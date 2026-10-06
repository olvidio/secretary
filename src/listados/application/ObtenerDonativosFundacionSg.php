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
        if ($totalCents !== 0) {
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
