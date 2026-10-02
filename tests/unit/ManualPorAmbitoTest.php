<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\ayuda\domain\entity\DocumentoAyuda;
use src\ayuda\domain\services\ManualPorAmbito;
use src\ayuda\domain\value_objects\AmbitoManual;
use src\ayuda\infrastructure\persistence\DocumentacionEnDisco;

final class ManualPorAmbitoTest extends TestCase
{
    public function testUnCentroSgNoRecibeElManualDelCentroN(): void
    {
        $filtro = new ManualPorAmbito();
        $docs = [
            new DocumentoAyuda('cierre-mes', 'Cierre', "# Cierre\n- Ámbito: centro-n\n\nGenerar apuntes de vivienda."),
            new DocumentoAyuda('aportaciones', 'Aportaciones', "# Aportaciones\n- Ámbito: centro-sg\n\nListado de aportaciones."),
            new DocumentoAyuda('entrar', 'Entrar', "# Entrar\n- Ámbito: todos\n\nCómo entrar."),
        ];

        $claves = array_map(
            static fn (DocumentoAyuda $d): string => $d->clave,
            $filtro->aplicar($docs, AmbitoManual::centroSg()),
        );

        self::assertSame(['aportaciones', 'entrar'], $claves);
        $aportaciones = $filtro->aplicar($docs, AmbitoManual::centroSg())[0]->texto;
        self::assertStringNotContainsString('Ámbito:', $aportaciones);
    }

    public function testRecortaElApartadoQueNoEsElSuyo(): void
    {
        $texto = <<<'MD'
# Apuntes
- Ámbito: centro-n, centro-sg

Común a los dos.

## Centro n

Filtros de libro P y G.

## Centro sg

Tabla del talonario.
MD;
        $recorte = (new ManualPorAmbito())->recortar($texto, AmbitoManual::centroSg());

        self::assertIsString($recorte);
        self::assertStringContainsString('Tabla del talonario.', $recorte);
        self::assertStringContainsString('Común a los dos.', $recorte);
        self::assertStringNotContainsString('libro P', $recorte);
        self::assertStringNotContainsString('## Centro n', $recorte);
    }

    public function testCadaFichaDelManualDeclaraAmbito(): void
    {
        $permitidos = ['todos', ...AmbitoManual::TODOS];
        $dir = dirname(__DIR__, 2) . '/docs/manual';
        $ficheros = glob($dir . '/*.md') ?: [];
        $sin = [];
        foreach ($ficheros as $fichero) {
            $clave = basename($fichero, '.md');
            if (str_starts_with($clave, '_')) {
                continue;
            }
            $texto = (string) file_get_contents($fichero);
            $declarados = ManualPorAmbito::ambitosDeclarados($texto);
            if ($declarados === null || $declarados === []) {
                $sin[] = $clave;
                continue;
            }
            foreach ($declarados as $codigo) {
                if (!in_array($codigo, $permitidos, true)) {
                    $sin[] = $clave . ':' . $codigo;
                }
            }
        }

        self::assertSame([], $sin, 'Fichas sin ámbito o con un ámbito desconocido');
    }

    public function testElManualRealDeUnCentroSgNoTraeElCentroN(): void
    {
        $docs = (new ManualPorAmbito())->aplicar(
            (new DocumentacionEnDisco(dirname(__DIR__, 2) . '/docs/manual'))->todos(),
            AmbitoManual::centroSg(),
        );
        $claves = array_map(static fn (DocumentoAyuda $d): string => $d->clave, $docs);
        foreach (['cierre-mes', 'e37', 'remesas-centro', 'disponible', 'procesos', 'centros'] as $fuera) {
            self::assertNotContains($fuera, $claves);
        }
        self::assertContains('aportaciones', $claves);
        self::assertContains('configuracion', $claves);
        $config = '';
        foreach ($docs as $doc) {
            if ($doc->clave === 'configuracion') {
                $config = $doc->texto;
            }
            if ($doc->clave === 'apuntes') {
                self::assertStringNotContainsString('P/211', $doc->texto);
                self::assertStringContainsString('talonario', $doc->texto);
            }
        }
        self::assertStringNotContainsString('Tramos de desgravación', $config);
        self::assertStringContainsString('Importar Excel', $config);
    }
}
