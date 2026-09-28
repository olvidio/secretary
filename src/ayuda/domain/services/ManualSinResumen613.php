<?php

declare(strict_types=1);

namespace src\ayuda\domain\services;

use src\ayuda\domain\entity\DocumentoAyuda;

/** Quita del manual las menciones al resumen 613, para una associació. */
final class ManualSinResumen613
{
    /**
     * @param list<DocumentoAyuda> $documentos
     * @return list<DocumentoAyuda>
     */
    public function aplicar(array $documentos): array
    {
        $out = [];
        foreach ($documentos as $documento) {
            if ($documento->clave === 'resumen-613' || str_contains($documento->titulo, '613')) {
                continue;
            }
            $lineas = preg_split('/\R/u', $documento->texto) ?: [];
            $limpias = [];
            foreach ($lineas as $linea) {
                if (str_contains($linea, '613')) {
                    continue;
                }
                $limpias[] = $linea;
            }
            $texto = trim(implode("\n", $limpias));
            if ($texto === '') {
                continue;
            }
            $out[] = new DocumentoAyuda($documento->clave, $documento->titulo, $texto);
        }

        return $out;
    }
}
