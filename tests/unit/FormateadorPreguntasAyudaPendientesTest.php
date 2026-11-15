<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\ayuda\domain\services\FormateadorPreguntasAyudaPendientes;
use src\ayuda\domain\value_objects\ComentarioPreguntaAyuda;
use src\ayuda\domain\value_objects\ConsultantePreguntaAyuda;
use src\ayuda\domain\value_objects\PreguntaAyudaPendiente;

final class FormateadorPreguntasAyudaPendientesTest extends TestCase
{
    public function testMarkdownIncluyePreguntaYMetadatos(): void
    {
        $fmt = new FormateadorPreguntasAyudaPendientes();
        $md = $fmt->markdown('abc123', [
            new PreguntaAyudaPendiente(
                '¿Cómo exporto el 613?',
                2,
                '2026-01-01 10:00:00',
                '2026-02-01 12:00:00',
                ['centro-n'],
                'Eso no está explicado…',
                [
                    new ConsultantePreguntaAyuda(42, 2, 'ds', 'd@e.local', 'Daniel'),
                ],
            ),
        ]);
        self::assertStringContainsString('abc123', $md);
        self::assertStringContainsString('¿Cómo exporto el 613?', $md);
        self::assertStringContainsString('centro-n', $md);
        self::assertStringContainsString('ds · d@e.local', $md);
        self::assertStringContainsString('docs/manual/', $md);
    }

    public function testMarkdownDeUnaRespuestaQueNoSirvioLlevaRespuestaYComentarios(): void
    {
        $md = (new FormateadorPreguntasAyudaPendientes())->markdown('abc123', [
            new PreguntaAyudaPendiente(
                '¿Cómo cierro el mes?',
                1,
                '2026-01-01 10:00:00',
                '2026-01-01 10:00:00',
                ['centro-n'],
                'En Cierre.',
                [],
                PreguntaAyudaPendiente::NO_SIRVIO,
                2,
                [
                    new ComentarioPreguntaAyuda('Quería saber lo del arqueo', 'ds · d@e.local', '2026-01-02 09:00:00'),
                    new ComentarioPreguntaAyuda('', '—', '2026-01-03 09:00:00'),
                ],
            ),
        ]);
        self::assertStringContainsString('no sirvió (2 «No»)', $md);
        self::assertStringContainsString('En Cierre.', $md);
        self::assertStringContainsString('Quería saber lo del arqueo — ds · d@e.local', $md);
        self::assertStringContainsString('_(sin comentario)_', $md);
    }
}
