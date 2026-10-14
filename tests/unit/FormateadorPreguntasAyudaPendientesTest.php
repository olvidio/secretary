<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\ayuda\domain\services\FormateadorPreguntasAyudaPendientes;
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
}
