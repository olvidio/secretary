<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\ayuda\application\RepasarPreguntasAyuda;
use src\ayuda\application\ValorarRespuestaAyuda;
use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\contracts\RepositorioDocumentacion;
use src\ayuda\domain\value_objects\AmbitoManual;
use src\ayuda\domain\value_objects\PreguntaAyuda;

final class ValorarRespuestaAyudaTest extends TestCase
{
    public function testElNoSeApuntaConLaMismaHuellaQueLaRespuestaYElComentarioRecortado(): void
    {
        $docs = $this->createMock(RepositorioDocumentacion::class);
        $docs->method('version')->willReturn('v1');
        $huella = (new PreguntaAyuda('¿Cómo cierro el mes?'))->huella('v1|' . AmbitoManual::centroSg()->codigo);

        $registro = $this->createMock(RegistroConsultasAyuda::class);
        $registro->expects(self::once())->method('valorar')
            ->with($huella, 7, false, self::callback(
                static fn (string $c): bool => mb_strlen($c) === ValorarRespuestaAyuda::MAXIMO_COMENTARIO
                    && str_starts_with($c, 'falta el arqueo'),
            ))
            ->willReturn(true);

        $ok = (new ValorarRespuestaAyuda($docs, $registro))->ejecutar(
            '  ¿Cómo cierro   el mes? ',
            false,
            "falta el\narqueo " . str_repeat('x', 600),
            7,
            AmbitoManual::centroSg(),
        );

        self::assertTrue($ok);
    }

    public function testElSiNoGuardaComentario(): void
    {
        $docs = $this->createMock(RepositorioDocumentacion::class);
        $docs->method('version')->willReturn('v1');
        $registro = $this->createMock(RegistroConsultasAyuda::class);
        $registro->expects(self::once())->method('valorar')
            ->with(self::anything(), null, true, '')
            ->willReturn(true);

        (new ValorarRespuestaAyuda($docs, $registro))->ejecutar('¿Cómo cierro el mes?', true, 'da igual');
    }

    public function testRepasarSoloAdmiteCubiertaODescartada(): void
    {
        $registro = $this->createMock(RegistroConsultasAyuda::class);
        $registro->expects(self::never())->method('marcarRepasada');

        $this->expectException(InvalidArgumentException::class);
        (new RepasarPreguntasAyuda($registro))->una('¿Dónde está Y?', 'nueva');
    }
}
