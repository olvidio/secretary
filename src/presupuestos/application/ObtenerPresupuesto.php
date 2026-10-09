<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use InvalidArgumentException;

final class ObtenerPresupuesto
{
    public function __construct(
        private readonly ConstruirHojaPrevision $hoja,
        private readonly GuardarPresupuesto $presupuesto,
        private readonly PresentarPresupuesto613P $presentar613P,
        private readonly PresentarPresupuesto613G $presentar613G,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(string $cuenta, ?string $etiquetaObjetivo = null): array
    {
        $cuenta = strtoupper($cuenta);
        $opts = $this->hoja->opcionesPrevision();
        $etiqueta = trim((string) ($etiquetaObjetivo ?? ''));
        if ($etiqueta === '') {
            $etiqueta = (string) ($opts['etiqueta_trabajo'] ?? $opts['etiqueta_defecto']);
        }
        $permitidas = $opts['etiquetas'];
        if (!in_array($etiqueta, $permitidas, true)) {
            throw new InvalidArgumentException(_("No hay ejercicio para el año elegido"));
        }
        $objetivo = $this->hoja->ejercicioPorEtiqueta($etiqueta);
        $ejercicioId = $objetivo?->id;

        $payload = array_merge($opts, [
            'etiqueta_presupuesto' => $etiqueta,
            'ejercicio_id' => $ejercicioId,
            'lineas' => $this->presupuesto->listarPorEjercicio($cuenta, $ejercicioId),
        ]);
        if ($cuenta === 'P') {
            $payload['vista_613'] = $this->presentar613P->ejecutar($ejercicioId, $etiqueta);
        } elseif ($cuenta === 'G') {
            $payload['vista_613'] = $this->presentar613G->ejecutar($ejercicioId, $etiqueta);
        }

        return $payload;
    }
}
