<?php

declare(strict_types=1);

namespace src\ayuda\domain\services;

use src\ayuda\domain\entity\DocumentoAyuda;
use src\ayuda\domain\value_objects\AmbitoManual;

/**
 * Deja en el manual solo lo que corresponde al tipo de cuenta que pregunta.
 *
 * Cada ficha declara `- Ámbito: …`. Si además tiene apartados `## Centro n`,
 * `## Centro sg`, `## Asociación y fundación`, `## Asociación`, `## Fundación`,
 * `## Libro personal` o `## Común`, se quitan los de otro tipo. Lo que va
 * antes del primer apartado de ese estilo lo ven todos los ámbitos de la ficha.
 * Una ficha sin línea de ámbito se trata como común (la usan los tests y un
 * manual a medio marcar).
 */
final class ManualPorAmbito
{
    /** @var array<string, list<string>|null> null = común a los ámbitos de la ficha */
    private const APARTADOS = [
        'Centro n' => [AmbitoManual::CENTRO_N],
        'Centro sg' => [AmbitoManual::CENTRO_SG],
        'Asociación y fundación' => [AmbitoManual::ASOCIACION, AmbitoManual::FUNDACION],
        'Asociación' => [AmbitoManual::ASOCIACION],
        'Fundación' => [AmbitoManual::FUNDACION],
        'Libro personal' => [AmbitoManual::PERSONA],
        'Común' => null,
    ];

    /**
     * @param list<DocumentoAyuda> $documentos
     * @return list<DocumentoAyuda>
     */
    public function aplicar(array $documentos, AmbitoManual $ambito): array
    {
        $out = [];
        foreach ($documentos as $documento) {
            $texto = $this->recortar($documento->texto, $ambito);
            if ($texto === null) {
                continue;
            }
            $out[] = new DocumentoAyuda($documento->clave, $documento->titulo, $texto);
        }

        return $out;
    }

    public function recortar(string $texto, AmbitoManual $ambito): ?string
    {
        $declarados = self::ambitosDeclarados($texto);
        if ($declarados !== null && !in_array('todos', $declarados, true) && !in_array($ambito->codigo, $declarados, true)) {
            return null;
        }
        $texto = self::quitarLineaAmbito($texto);
        $texto = $this->quitarApartadosAjenos($texto, $ambito);
        $texto = trim($texto);

        return $texto === '' ? null : $texto;
    }

    /**
     * @return list<string>|null null si la ficha no declara ámbito
     */
    public static function ambitosDeclarados(string $texto): ?array
    {
        if (preg_match('/^- Ámbito:\s*(.+)$/mu', $texto, $m) !== 1) {
            return null;
        }
        $trozos = preg_split('/\s*,\s*/u', trim($m[1])) ?: [];
        $out = [];
        foreach ($trozos as $trozo) {
            $codigo = trim($trozo);
            if ($codigo === '') {
                continue;
            }
            $out[] = $codigo;
        }

        return $out;
    }

    private function quitarApartadosAjenos(string $texto, AmbitoManual $ambito): string
    {
        $lineas = preg_split('/\R/u', $texto) ?: [];
        $tiene = false;
        foreach ($lineas as $linea) {
            if ($this->apartado($linea) !== false) {
                $tiene = true;
                break;
            }
        }
        if (!$tiene) {
            return $texto;
        }
        $quedan = [];
        $incluir = true;
        foreach ($lineas as $linea) {
            $apartado = $this->apartado($linea);
            if ($apartado !== false) {
                $permitidos = self::APARTADOS[$apartado];
                $incluir = $permitidos === null || in_array($ambito->codigo, $permitidos, true);
                if (!$incluir) {
                    continue;
                }
            }
            if ($incluir) {
                $quedan[] = $linea;
            }
        }

        return implode("\n", $quedan);
    }

    private function apartado(string $linea): string|false
    {
        if (preg_match('/^##\s+(.+?)\s*$/u', $linea, $m) !== 1) {
            return false;
        }
        $titulo = $m[1];

        return array_key_exists($titulo, self::APARTADOS) ? $titulo : false;
    }

    private static function quitarLineaAmbito(string $texto): string
    {
        $sin = preg_replace('/^- Ámbito:\s*.+$/mu', '', $texto);

        return is_string($sin) ? $sin : $texto;
    }
}
