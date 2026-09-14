<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;

/**
 * Cada pantalla GET tiene que citarse en la línea «Ruta:» de algún fichero
 * de docs/manual. Si se añade una vista y se olvida el manual, la ayuda de IA
 * no puede contestar sobre ella.
 */
final class ManualCubrePantallasTest extends TestCase
{
    public function testCadaRutaDePantallaEstaEnElManual(): void
    {
        $root = dirname(__DIR__, 2);
        $rutas = $this->rutasDePantalla($root . '/frontend/shared/config/routes.php');
        $citadas = $this->rutasDelManual($root . '/docs/manual');
        $faltan = array_values(array_diff($rutas, $citadas));

        self::assertSame(
            [],
            $faltan,
            'Pantallas sin ficha en docs/manual (añada «- Ruta: …» en el .md correspondiente)',
        );
    }

    /** @return list<string> */
    private function rutasDePantalla(string $fichero): array
    {
        $texto = (string) file_get_contents($fichero);
        preg_match_all("#addRoute\\('GET', '(/[^']*)'#", $texto, $desdeAdd);
        preg_match_all("#\\['(/[^']*)', '[^']+', '[^']+'\\]#", $texto, $desdeLista);
        $rutas = array_values(array_unique(array_merge($desdeAdd[1], $desdeLista[1])));
        sort($rutas);

        return $rutas;
    }

    /** @return list<string> */
    private function rutasDelManual(string $directorio): array
    {
        $citadas = [];
        foreach (glob($directorio . '/*.md') ?: [] as $fichero) {
            $texto = (string) file_get_contents($fichero);
            if (preg_match_all('/^- Ruta:.*$/m', $texto, $lineas) === 0) {
                continue;
            }
            foreach ($lineas[0] as $linea) {
                if (preg_match_all('#`(/[^`]*)`#', $linea, $coincidencias) === 0) {
                    continue;
                }
                foreach ($coincidencias[1] as $ruta) {
                    $citadas[] = $ruta;
                }
            }
        }

        return array_values(array_unique($citadas));
    }
}
