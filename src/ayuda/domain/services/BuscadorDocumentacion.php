<?php

declare(strict_types=1);

namespace src\ayuda\domain\services;

use src\ayuda\domain\entity\DocumentoAyuda;
use src\ayuda\domain\value_objects\PreguntaAyuda;

/**
 * Búsqueda por palabras sobre el manual. Se usa cuando el modelo no está
 * disponible: sin IA, pero el usuario se va con el apartado en la mano.
 */
final class BuscadorDocumentacion
{
    private const LONGITUD_MINIMA = 4;
    private const PESO_TITULO = 5;

    /** Palabras demasiado comunes para discriminar entre apartados. */
    private const VACIAS = [
        'como', 'cual', 'cuales', 'cuando', 'cuanto', 'desde', 'donde', 'esta', 'estan', 'este',
        'esto', 'hace', 'hacer', 'hasta', 'para', 'pero', 'porque', 'puede', 'pueden', 'quien',
        'sobre', 'tiene', 'tienen', 'todos', 'entre', 'programa', 'pantalla',
    ];

    /**
     * @param list<DocumentoAyuda> $documentos
     * @return list<DocumentoAyuda>
     */
    public function buscar(array $documentos, PreguntaAyuda $pregunta, int $limite = 3): array
    {
        $terminos = $this->terminos($pregunta->texto);
        if ($terminos === []) {
            return [];
        }
        $puntuados = [];
        foreach ($documentos as $indice => $documento) {
            $puntos = $this->puntuar($documento, $terminos);
            if ($puntos > 0) {
                $puntuados[] = ['puntos' => $puntos, 'indice' => $indice, 'documento' => $documento];
            }
        }
        usort(
            $puntuados,
            static fn (array $a, array $b): int => [$b['puntos'], $a['indice']] <=> [$a['puntos'], $b['indice']],
        );

        return array_map(
            static fn (array $fila): DocumentoAyuda => $fila['documento'],
            array_slice($puntuados, 0, max(1, $limite)),
        );
    }

    /**
     * @param list<string> $terminos
     */
    private function puntuar(DocumentoAyuda $documento, array $terminos): int
    {
        $titulo = self::normalizar($documento->titulo . ' ' . str_replace('-', ' ', $documento->clave));
        $cuerpo = self::normalizar($documento->texto);
        $puntos = 0;
        foreach ($terminos as $termino) {
            $puntos += self::PESO_TITULO * substr_count($titulo, $termino);
            $puntos += substr_count($cuerpo, $termino);
        }

        return $puntos;
    }

    /** @return list<string> */
    private function terminos(string $texto): array
    {
        $partes = preg_split('/[^\p{L}\p{N}]+/u', self::normalizar($texto)) ?: [];
        $out = [];
        foreach ($partes as $parte) {
            if (strlen($parte) < self::LONGITUD_MINIMA || in_array($parte, self::VACIAS, true)) {
                continue;
            }
            if (!in_array($parte, $out, true)) {
                $out[] = $parte;
            }
        }

        return $out;
    }

    private static function normalizar(string $texto): string
    {
        return NormalizadorTexto::plano($texto);
    }
}
