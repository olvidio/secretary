<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\ayuda\domain\entity\DocumentoAyuda;
use src\ayuda\domain\services\BuscadorDocumentacion;
use src\ayuda\domain\value_objects\PreguntaAyuda;

final class BuscadorDocumentacionTest extends TestCase
{
    /** @return list<DocumentoAyuda> */
    private function manual(): array
    {
        return [
            new DocumentoAyuda(
                'apuntes',
                'Apuntes',
                'Listado de apuntes del centro. Se puede editar y borrar, salvo los del cierre.',
            ),
            new DocumentoAyuda('traspasos', 'Traspasos', 'Mueve dinero entre el banco y la caja del centro.'),
            new DocumentoAyuda('cierre-mes', 'Cierre de mes', 'Carga la vivienda a quien aporta a generales.'),
        ];
    }

    public function testEncuentraPorElTitulo(): void
    {
        $encontrados = (new BuscadorDocumentacion())->buscar(
            $this->manual(),
            new PreguntaAyuda('¿Cómo hago un traspaso?'),
            1,
        );

        self::assertCount(1, $encontrados);
        self::assertSame('traspasos', $encontrados[0]->clave);
    }

    public function testElTituloPesaMasQueElCuerpo(): void
    {
        $encontrados = (new BuscadorDocumentacion())->buscar(
            $this->manual(),
            new PreguntaAyuda('dudas sobre el cierre'),
            1,
        );

        self::assertSame('cierre-mes', $encontrados[0]->clave);
    }

    public function testEncuentraAunqueFaltenLosAcentos(): void
    {
        $encontrados = (new BuscadorDocumentacion())->buscar(
            $this->manual(),
            new PreguntaAyuda('quien aporta vivienda a generales'),
            1,
        );

        self::assertSame('cierre-mes', $encontrados[0]->clave);
    }

    public function testSinCoincidenciasDevuelveVacio(): void
    {
        $encontrados = (new BuscadorDocumentacion())->buscar(
            $this->manual(),
            new PreguntaAyuda('¿cuánto mide el Teide?'),
        );

        self::assertSame([], $encontrados);
    }

    public function testLasPalabrasComunesNoCuentan(): void
    {
        $encontrados = (new BuscadorDocumentacion())->buscar(
            $this->manual(),
            new PreguntaAyuda('¿cómo se hace esto?'),
        );

        self::assertSame([], $encontrados);
    }

    public function testRespetaElLimitePedido(): void
    {
        $encontrados = (new BuscadorDocumentacion())->buscar(
            $this->manual(),
            new PreguntaAyuda('dinero del centro y apuntes de la caja'),
            2,
        );

        self::assertCount(2, $encontrados);
    }
}
