<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\legal\application\ExigirDeclaracionResponsableNombres;
use src\legal\application\LecturaAceptacion;
use src\legal\application\RegistrarAceptacion;
use src\legal\domain\contracts\AceptacionLegalRepository;
use src\legal\domain\entity\AceptacionLegal;
use src\legal\domain\services\CatalogoDocumentosLegales;
use src\legal\domain\services\DatosOperador;
use src\legal\domain\value_objects\HuellaAceptacion;
use src\legal\infrastructure\markdown\RenderizadorMarkdownLegal;

final class DocumentosLegalesTest extends TestCase
{
    public function testCatalogoTieneCondicionesYPrivacidadEnDosIdiomas(): void
    {
        $catalogo = CatalogoDocumentosLegales::porDefecto();
        $tipos = [];
        foreach ($catalogo->todos() as $doc) {
            $tipos[$doc->tipo . '.' . $doc->idioma] = $doc;
            self::assertSame(64, strlen($doc->hashSha256));
            self::assertNotSame('', $doc->texto);
        }
        self::assertArrayHasKey('condiciones.es', $tipos);
        self::assertArrayHasKey('condiciones.ca', $tipos);
        self::assertArrayHasKey('privacidad.es', $tipos);
        self::assertArrayHasKey('privacidad.ca', $tipos);
        self::assertSame(CatalogoDocumentosLegales::VERSION_VIGENTE, $catalogo->vigente('condiciones')->version);
    }

    public function testLecturaAceptacion(): void
    {
        self::assertTrue(LecturaAceptacion::marcada('1'));
        self::assertTrue(LecturaAceptacion::marcada('on'));
        self::assertTrue(LecturaAceptacion::marcada(true));
        self::assertFalse(LecturaAceptacion::marcada(''));
        self::assertFalse(LecturaAceptacion::marcada(null));
        self::assertFalse(LecturaAceptacion::marcada('0'));
    }

    public function testExigeDeclaracionDeNombres(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $catalogo = CatalogoDocumentosLegales::porDefecto();
        $aceptaciones = $this->createMock(AceptacionLegalRepository::class);
        $aceptaciones->expects(self::never())->method('registrar');
        $caso = new ExigirDeclaracionResponsableNombres(
            new RegistrarAceptacion($aceptaciones, $catalogo, new DatosOperador('Op', 'op@x.local', 'Dir')),
            $catalogo,
        );
        $caso->ejecutar(false, 1, 'nombres_alta', new HuellaAceptacion(centroId: 9));
    }

    public function testRegistraDeclaracionDeNombres(): void
    {
        $catalogo = CatalogoDocumentosLegales::porDefecto();
        $aceptaciones = $this->createMock(AceptacionLegalRepository::class);
        $aceptaciones->expects(self::once())->method('registrar')->with(self::callback(
            static function (AceptacionLegal $a): bool {
                return $a->canal === 'nombres_alta'
                    && $a->huella->centroId === 9
                    && str_contains($a->textoCasilla, 'responsables');
            }
        ));
        $caso = new ExigirDeclaracionResponsableNombres(
            new RegistrarAceptacion($aceptaciones, $catalogo, new DatosOperador('Op', 'op@x.local', 'Dir')),
            $catalogo,
        );
        $caso->ejecutar(true, 1, 'nombres_alta', new HuellaAceptacion(centroId: 9));
    }

    public function testRenderizaMarkdownBasico(): void
    {
        $html = RenderizadorMarkdownLegal::aHtml("# Título\n\nHola **mundo**.\n\n- uno\n- dos");
        self::assertStringContainsString('<h1>Título</h1>', $html);
        self::assertStringContainsString('<strong>mundo</strong>', $html);
        self::assertStringContainsString('<li>uno</li>', $html);
    }
}
