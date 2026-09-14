<?php

declare(strict_types=1);

namespace src\ayuda\application;

use src\ayuda\domain\contracts\RepositorioDocumentacion;

/** Índice del manual: alimenta la lista de apartados de la pantalla de ayuda. */
final class ListarTemasAyuda
{
    public function __construct(private readonly RepositorioDocumentacion $documentacion)
    {
    }

    /**
     * @return list<array{clave: string, titulo: string}>
     */
    public function ejecutar(): array
    {
        $out = [];
        foreach ($this->documentacion->todos() as $documento) {
            $out[] = ['clave' => $documento->clave, 'titulo' => $documento->titulo];
        }

        return $out;
    }

    /**
     * Títulos por clave, para mostrar las fuentes citadas en una respuesta.
     *
     * @return array<string, string>
     */
    public function titulos(): array
    {
        $out = [];
        foreach ($this->documentacion->todos() as $documento) {
            $out[$documento->clave] = $documento->titulo;
        }

        return $out;
    }
}
