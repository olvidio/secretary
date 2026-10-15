<?php

declare(strict_types=1);

namespace src\presupuestos\application;

use src\informes\domain\services\CalculadoraE37;
use src\presupuestos\domain\services\PrevisionResumenE37;
use src\shared\domain\value_objects\Dinero;

/** Resumen E37 (una fila por persona) a partir de las previsiones personales guardadas. */
final class ObtenerPrevisionResumenE37
{
    public function __construct(
        private readonly ObtenerPrevisionConsolidada $consolidada,
    ) {
    }

    /** @return array<string, mixed> */
    public function ejecutar(?string $etiquetaObjetivo = null): array
    {
        $base = $this->consolidada->ejecutar($etiquetaObjetivo);
        $filas = [];
        foreach ($base['personas'] as $p) {
            $previstoPorCodigo = [];
            foreach ($base['lineas'] as $linea) {
                $codigo = (string) $linea['codigo'];
                foreach ($linea['personas'] as $pp) {
                    if ((int) $pp['persona_id'] === (int) $p['id']) {
                        $previstoPorCodigo[$codigo] = (int) $pp['previsto_cents'];
                        break;
                    }
                }
            }
            $filas[] = PrevisionResumenE37::filaPersona(
                (string) $p['nombre'],
                (string) $p['iniciales'],
                $previstoPorCodigo,
            );
        }

        $aviso = null;
        foreach ($filas as $fila) {
            if (Dinero::fromInput((string) ($fila['saldo_cc'] ?? '0'))->isNegative()) {
                $aviso = CalculadoraE37::avisoSaldoCcNegativo();
                break;
            }
        }

        return [
            'ejercicio_id' => $base['ejercicio_id'],
            'etiqueta_presupuesto' => $base['etiqueta_presupuesto'],
            'etiqueta_defecto' => $base['etiqueta_defecto'],
            'etiqueta_trabajo' => $base['etiqueta_trabajo'],
            'etiquetas' => $base['etiquetas'],
            'anio_presupuesto' => $base['anio_presupuesto'],
            'anios_disponibles' => $base['anios_disponibles'],
            'pendientes' => $base['pendientes'],
            'filas' => $filas,
            'columnas' => CalculadoraE37::columnasHoja(),
            'aviso_saldo_cc' => $aviso,
        ];
    }
}
