<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use src\personas\domain\contracts\PersonaRepository;
use src\presupuestos\domain\contracts\PrevisionPersonalRepository;
use src\presupuestos\domain\services\AgrupadorPrevision613P;
use src\shared\domain\value_objects\Dinero;

final class ObtenerPrevisionConsolidada
{
    public function __construct(
        private readonly ConstruirHojaPrevision $hoja,
        private readonly PersonaRepository $personas,
        private readonly PrevisionPersonalRepository $prevision,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(): array
    {
        $datos = $this->hoja->contextoEstructura();
        $etiqueta = $this->hoja->etiquetaPresupuestoPorDefecto();
        $objetivo = $this->hoja->ejercicioPorEtiqueta($etiqueta);
        $ejercicioId = $objetivo !== null && $objetivo->id !== null ? (int) $objetivo->id : 0;
        $personas = [];
        foreach ($this->personas->listarDeCentro($datos['centro_id']) as $p) {
            if ($p->id === null) {
                continue;
            }
            $personas[] = [
                'id' => $p->id,
                'iniciales' => $p->iniciales,
                'nombre' => $p->nombreCompleto(),
                'nombre_corto' => $p->nombre,
            ];
        }
        $guardado = [];
        $personasConPrevision = [];
        $lineasGuardadas = $ejercicioId > 0 ? $this->prevision->listarDeEjercicio($ejercicioId) : [];
        foreach ($lineasGuardadas as $l) {
            $guardado[$l->personaId][$l->conceptoCodigo] = $l->previstoCents;
            $personasConPrevision[$l->personaId] = true;
        }
        $lineas = [];
        $paraAgrupar = [];
        foreach ($datos['estructura'] as $def) {
            $codigo = $def['codigo'];
            $porPersona = [];
            $centsPersona = [];
            $total = 0;
            foreach ($personas as $p) {
                $cents = $guardado[$p['id']][$codigo] ?? 0;
                $dinero = Dinero::fromCents($cents);
                $porPersona[] = [
                    'persona_id' => $p['id'],
                    'previsto' => $dinero->toString(),
                    'previsto_es' => $dinero->formatEs(),
                    'previsto_cents' => $cents,
                ];
                $centsPersona[] = $cents;
                $total += $cents;
            }
            $totDinero = Dinero::fromCents($total);
            $grupo = (string) ($def['grupo'] ?? AgrupadorPrevision613P::grupoDe($codigo));
            $lineas[] = [
                'codigo' => $codigo,
                'etiqueta' => $def['etiqueta'],
                'grupo' => $grupo,
                'total' => $totDinero->toString(),
                'total_es' => $totDinero->formatEs(),
                'total_cents' => $total,
                'personas' => $porPersona,
            ];
            $paraAgrupar[] = [
                'codigo' => $codigo,
                'etiqueta' => $def['etiqueta'],
                'grupo' => $grupo,
                'total_cents' => $total,
                'personas_cents' => $centsPersona,
            ];
        }
        $pendientes = [];
        foreach ($personas as $p) {
            if (!isset($personasConPrevision[$p['id']])) {
                $pendientes[] = $p['nombre'];
            }
        }
        $filas = [];
        foreach (AgrupadorPrevision613P::filas(AgrupadorPrevision613P::filtrar212SinUso($paraAgrupar)) as $f) {
            $filas[] = self::formatearFila($f, $personas);
        }

        return [
            'ejercicio_id' => $ejercicioId > 0 ? $ejercicioId : null,
            'etiqueta_presupuesto' => $etiqueta,
            'anio_presupuesto' => $etiqueta,
            'personas' => $personas,
            'lineas' => $lineas,
            'filas' => $filas,
            'pendientes' => $pendientes,
        ];
    }

    /**
     * @param array{tipo:string,grupo:string,codigo:?string,etiqueta:string,total_cents:int,personas_cents:list<int>} $f
     * @param list<array{id:int}> $personas
     * @return array<string, mixed>
     */
    private static function formatearFila(array $f, array $personas): array
    {
        $tot = Dinero::fromCents($f['total_cents']);
        $porPersona = [];
        foreach ($personas as $i => $p) {
            $dinero = Dinero::fromCents($f['personas_cents'][$i] ?? 0);
            $porPersona[] = [
                'persona_id' => $p['id'],
                'previsto' => $dinero->toString(),
                'previsto_es' => $dinero->formatEs(),
                'previsto_cents' => $dinero->toCents(),
            ];
        }

        return [
            'tipo' => $f['tipo'],
            'grupo' => $f['grupo'],
            'codigo' => $f['codigo'],
            'etiqueta' => $f['etiqueta'],
            'total' => $tot->toString(),
            'total_es' => $tot->formatEs(),
            'total_cents' => $f['total_cents'],
            'personas' => $porPersona,
        ];
    }
}
