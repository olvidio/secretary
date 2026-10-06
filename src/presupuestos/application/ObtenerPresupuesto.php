<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use InvalidArgumentException;

final class ObtenerPresupuesto
{
    public function __construct(
        private readonly ConstruirHojaPrevision $hoja,
        private readonly GuardarPresupuesto $presupuesto,
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

        return array_merge($opts, [
            'etiqueta_presupuesto' => $etiqueta,
            'ejercicio_id' => $ejercicioId,
            'lineas' => $this->presupuesto->listarPorEjercicio($cuenta, $ejercicioId),
        ]);
    }
}
