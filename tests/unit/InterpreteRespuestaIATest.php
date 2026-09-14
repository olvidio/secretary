<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\ayuda\domain\services\InterpreteRespuestaIA;
use src\ayuda\domain\value_objects\OrigenRespuesta;

final class InterpreteRespuestaIATest extends TestCase
{
    private const CLAVES = ['apuntes', 'traspasos', 'cierre-mes'];

    public function testSeparaLaRespuestaDeLasFuentes(): void
    {
        $crudo = "Se anota con el concepto 41.\nEl programa añade el apunte espejo.\nFUENTES: traspasos";

        $respuesta = (new InterpreteRespuestaIA())->interpretar($crudo, self::CLAVES);

        self::assertTrue($respuesta->resuelta);
        self::assertSame(['traspasos'], $respuesta->fuentes);
        self::assertSame(
            "Se anota con el concepto 41.\nEl programa añade el apunte espejo.",
            $respuesta->texto,
        );
        self::assertSame(OrigenRespuesta::Ia, $respuesta->origen);
    }

    public function testAceptaVariasFuentesConAdornos(): void
    {
        $crudo = "Primero el apunte y luego el cierre.\n**FUENTES:** `apuntes`, [cierre-mes].";

        $respuesta = (new InterpreteRespuestaIA())->interpretar($crudo, self::CLAVES);

        self::assertSame(['apuntes', 'cierre-mes'], $respuesta->fuentes);
        self::assertSame('Primero el apunte y luego el cierre.', $respuesta->texto);
    }

    public function testLaMarcaSinRespuestaNoResuelve(): void
    {
        $respuesta = (new InterpreteRespuestaIA())->interpretar('SIN_RESPUESTA', self::CLAVES);

        self::assertFalse($respuesta->resuelta);
        self::assertSame([], $respuesta->fuentes);
    }

    /** El candado del módulo: sin documento real que la respalde, no se muestra. */
    public function testDescartaLaRespuestaQueCitaDocumentosInexistentes(): void
    {
        $crudo = "Vaya al menú Contabilidad avanzada y pulse Recalcular.\nFUENTES: contabilidad-avanzada";

        $respuesta = (new InterpreteRespuestaIA())->interpretar($crudo, self::CLAVES);

        self::assertFalse($respuesta->resuelta);
        self::assertStringNotContainsString('Contabilidad avanzada', $respuesta->texto);
    }

    public function testDescartaLaRespuestaSinLineaDeFuentes(): void
    {
        $respuesta = (new InterpreteRespuestaIA())->interpretar('Me lo acabo de inventar.', self::CLAVES);

        self::assertFalse($respuesta->resuelta);
    }

    public function testRespuestaVaciaNoResuelve(): void
    {
        $respuesta = (new InterpreteRespuestaIA())->interpretar("  \n ", self::CLAVES);

        self::assertFalse($respuesta->resuelta);
    }

    public function testNoRepiteLaMismaFuenteCitadaDosVeces(): void
    {
        $crudo = "Se anota en Apuntes.\nFUENTES: apuntes, apuntes";

        $respuesta = (new InterpreteRespuestaIA())->interpretar($crudo, self::CLAVES);

        self::assertSame(['apuntes'], $respuesta->fuentes);
    }
}
