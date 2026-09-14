<?php

declare(strict_types=1);

namespace src\ayuda\infrastructure\persistence;

use src\ayuda\domain\contracts\RepositorioDocumentacion;
use src\ayuda\domain\entity\DocumentoAyuda;

/**
 * El manual de `docs/manual` es la única fuente de la ayuda. Está en el repo a
 * propósito: se revisa en los mismos cambios que el código que documenta.
 */
final class DocumentacionEnDisco implements RepositorioDocumentacion
{
    /** @var list<DocumentoAyuda>|null */
    private ?array $memoria = null;

    public function __construct(private readonly string $directorio)
    {
    }

    public static function porDefecto(): self
    {
        return new self(dirname(__DIR__, 4) . '/docs/manual');
    }

    /** @return list<DocumentoAyuda> */
    public function todos(): array
    {
        if ($this->memoria !== null) {
            return $this->memoria;
        }
        $ficheros = glob(rtrim($this->directorio, '/') . '/*.md') ?: [];
        sort($ficheros);
        $out = [];
        foreach ($ficheros as $fichero) {
            $clave = basename($fichero, '.md');
            if (str_starts_with($clave, '_')) {
                continue;
            }
            $texto = trim((string) file_get_contents($fichero));
            if ($texto === '') {
                continue;
            }
            $out[] = new DocumentoAyuda($clave, self::titulo($texto, $clave), $texto);
        }

        return $this->memoria = $out;
    }

    public function version(): string
    {
        $acumulado = '';
        foreach ($this->todos() as $documento) {
            $acumulado .= $documento->clave . ':' . md5($documento->texto) . "\n";
        }

        return substr(hash('sha256', $acumulado), 0, 16);
    }

    private static function titulo(string $texto, string $clave): string
    {
        if (preg_match('/^#\s+(.+)$/mu', $texto, $coincidencias) === 1) {
            return trim($coincidencias[1]);
        }

        return ucfirst(str_replace('-', ' ', $clave));
    }
}
