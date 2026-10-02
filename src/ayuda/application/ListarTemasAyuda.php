<?php

declare(strict_types=1);

namespace src\ayuda\application;

use src\ayuda\domain\contracts\RepositorioDocumentacion;
use src\ayuda\domain\services\ManualPorAmbito;
use src\ayuda\domain\value_objects\AmbitoManual;

/** Índice del manual: alimenta la lista de apartados de la pantalla de ayuda. */
final class ListarTemasAyuda
{
    public function __construct(private readonly RepositorioDocumentacion $documentacion)
    {
    }

    /**
     * @return list<array{clave: string, titulo: string}>
     */
    public function ejecutar(?AmbitoManual $ambito = null): array
    {
        $out = [];
        $documentos = $this->documentos($ambito);
        foreach ($documentos as $documento) {
            $out[] = ['clave' => $documento->clave, 'titulo' => $documento->titulo];
        }

        return $out;
    }

    /**
     * Títulos por clave, para mostrar las fuentes citadas en una respuesta.
     *
     * @return array<string, string>
     */
    public function titulos(?AmbitoManual $ambito = null): array
    {
        $out = [];
        $documentos = $this->documentos($ambito);
        foreach ($documentos as $documento) {
            $out[$documento->clave] = $documento->titulo;
        }

        return $out;
    }

    /** @return list<\src\ayuda\domain\entity\DocumentoAyuda> */
    private function documentos(?AmbitoManual $ambito): array
    {
        $ambito ??= AmbitoManual::centroN();

        return (new ManualPorAmbito())->aplicar($this->documentacion->todos(), $ambito);
    }
}
