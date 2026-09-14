<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\ayuda\infrastructure\persistence\DocumentacionEnDisco;

/** El manual real es el corpus de la ayuda: si se rompe, la ayuda miente o calla. */
final class DocumentacionEnDiscoTest extends TestCase
{
    public function testElManualDelRepoSeLeeEntero(): void
    {
        $documentos = DocumentacionEnDisco::porDefecto()->todos();

        self::assertNotSame([], $documentos, 'No se encuentra el manual en docs/manual');
        foreach ($documentos as $documento) {
            self::assertMatchesRegularExpression(
                '/^[a-z0-9-]+$/',
                $documento->clave,
                'Clave poco citable: ' . $documento->clave,
            );
            self::assertStringStartsWith(
                '# ',
                $documento->texto,
                'Falta el encabezado «# Título» en ' . $documento->clave . '.md',
            );
        }
    }

    public function testLasClavesNoSeRepiten(): void
    {
        $claves = array_map(
            static fn ($documento): string => $documento->clave,
            DocumentacionEnDisco::porDefecto()->todos(),
        );

        self::assertSame($claves, array_values(array_unique($claves)));
    }

    public function testLaVersionCambiaAlCambiarElManual(): void
    {
        $directorio = sys_get_temp_dir() . '/ayuda_' . bin2hex(random_bytes(4));
        mkdir($directorio);
        file_put_contents($directorio . '/apuntes.md', "# Apuntes\nPrimera versión.");

        $version = (new DocumentacionEnDisco($directorio))->version();
        file_put_contents($directorio . '/apuntes.md', "# Apuntes\nSegunda versión.");
        $nueva = (new DocumentacionEnDisco($directorio))->version();

        unlink($directorio . '/apuntes.md');
        rmdir($directorio);
        self::assertNotSame($version, $nueva);
    }

    public function testElTituloSaleDelPrimerEncabezado(): void
    {
        $directorio = sys_get_temp_dir() . '/ayuda_' . bin2hex(random_bytes(4));
        mkdir($directorio);
        file_put_contents($directorio . '/cierre-mes.md', "# Cierre de mes\n\nCarga la vivienda.");

        $documentos = (new DocumentacionEnDisco($directorio))->todos();

        unlink($directorio . '/cierre-mes.md');
        rmdir($directorio);
        self::assertCount(1, $documentos);
        self::assertSame('cierre-mes', $documentos[0]->clave);
        self::assertSame('Cierre de mes', $documentos[0]->titulo);
    }
}
