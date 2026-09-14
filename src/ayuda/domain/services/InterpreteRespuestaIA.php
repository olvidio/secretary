<?php

declare(strict_types=1);

namespace src\ayuda\domain\services;

use src\ayuda\domain\value_objects\OrigenRespuesta;
use src\ayuda\domain\value_objects\RespuestaAyuda;

/**
 * Convierte el texto crudo del modelo en respuesta, y hace de candado: una
 * respuesta que no cite ningún documento existente se descarta. No garantiza
 * que el modelo no invente, pero sí que no se muestre nada sin respaldo.
 */
final class InterpreteRespuestaIA
{
    public const MAXIMO_TEXTO = 4000;

    /** @param list<string> $clavesValidas */
    public function interpretar(string $crudo, array $clavesValidas): RespuestaAyuda
    {
        $texto = trim($crudo);
        if ($texto === '') {
            return RespuestaAyuda::sinRespuesta(OrigenRespuesta::Ia);
        }
        [$cuerpo, $citadas] = $this->separarFuentes($texto);
        $cuerpo = trim($cuerpo);
        if ($cuerpo === '' || $this->esSinRespuesta($cuerpo)) {
            return RespuestaAyuda::sinRespuesta(OrigenRespuesta::Ia);
        }
        $fuentes = $this->filtrarClaves($citadas, $clavesValidas);
        if ($fuentes === []) {
            return RespuestaAyuda::sinRespuesta(OrigenRespuesta::Ia);
        }

        return new RespuestaAyuda(mb_substr($cuerpo, 0, self::MAXIMO_TEXTO), $fuentes, OrigenRespuesta::Ia);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function separarFuentes(string $texto): array
    {
        $lineas = preg_split('/\R/u', $texto);
        if ($lineas === false) {
            return [$texto, []];
        }
        $citadas = [];
        for ($i = count($lineas) - 1; $i >= 0; $i--) {
            $linea = trim($lineas[$i], " \t*_-");
            if ($linea === '') {
                continue;
            }
            $patron = '/^' . ConstructorPromptAyuda::MARCA_FUENTES . '\s*:\s*(.*)$/iu';
            if (preg_match($patron, $linea, $coincidencias) !== 1) {
                break;
            }
            $citadas = preg_split('/[,;]+/u', $coincidencias[1]) ?: [];
            array_splice($lineas, $i, 1);
            break;
        }

        return [implode("\n", $lineas), $citadas];
    }

    private function esSinRespuesta(string $cuerpo): bool
    {
        return preg_match('/^' . ConstructorPromptAyuda::MARCA_SIN_RESPUESTA . '\b/iu', $cuerpo) === 1;
    }

    /**
     * @param list<string> $citadas
     * @param list<string> $clavesValidas
     * @return list<string>
     */
    private function filtrarClaves(array $citadas, array $clavesValidas): array
    {
        $validas = [];
        foreach ($clavesValidas as $clave) {
            $validas[mb_strtolower($clave, 'UTF-8')] = $clave;
        }
        $out = [];
        foreach ($citadas as $citada) {
            $limpia = mb_strtolower(trim($citada, " \t`'\"[]().*_#"), 'UTF-8');
            if (isset($validas[$limpia]) && !in_array($validas[$limpia], $out, true)) {
                $out[] = $validas[$limpia];
            }
        }

        return $out;
    }
}
