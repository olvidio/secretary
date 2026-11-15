<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use src\ayuda\application\ReintentarPreguntasAyudaPendientes;
use src\ayuda\domain\contracts\ProveedorRespuestaIA;
use src\ayuda\domain\contracts\RegistroConsultasAyuda;
use src\ayuda\domain\contracts\RepositorioDocumentacion;
use src\ayuda\domain\entity\DocumentoAyuda;
use src\ayuda\domain\services\ConstructorPromptAyuda;
use src\ayuda\domain\services\InterpreteRespuestaIA;
use src\ayuda\domain\value_objects\EstadoRepasoAyuda;
use src\ayuda\domain\value_objects\PreguntaAyudaPendiente;

final class ReintentarPreguntasAyudaPendientesTest extends TestCase
{
    private function caso(RegistroConsultasAyuda $registro, ?ProveedorRespuestaIA $proveedor): ReintentarPreguntasAyudaPendientes
    {
        $manual = $this->createMock(RepositorioDocumentacion::class);
        $manual->method('todos')->willReturn([
            new DocumentoAyuda('traspasos', 'Traspasos', "# Traspasos\nMueve dinero entre banco y caja."),
        ]);

        return new ReintentarPreguntasAyudaPendientes(
            $manual,
            $registro,
            new ConstructorPromptAyuda(),
            new InterpreteRespuestaIA(),
            $proveedor,
        );
    }

    private function pendiente(string $pregunta, string $motivo = PreguntaAyudaPendiente::SIN_RESPUESTA): PreguntaAyudaPendiente
    {
        return new PreguntaAyudaPendiente($pregunta, 1, '2026-01-01 10:00:00', '2026-01-01 10:00:00', [], '', [], $motivo);
    }

    public function testCierraLasQueAhoraSeContestanYDejaLasDemas(): void
    {
        $registro = $this->createMock(RegistroConsultasAyuda::class);
        $registro->method('listarPendientes')->willReturn([
            $this->pendiente('¿Cómo paso dinero a la caja?'),
            $this->pendiente('¿Cuál es la capital de Francia?'),
            $this->pendiente('me sale mal el traspaso'),
            $this->pendiente('la respuesta era mala', PreguntaAyudaPendiente::NO_SIRVIO),
        ]);
        $cerradas = [];
        $registro->method('marcarRepasada')->willReturnCallback(
            static function (string $pregunta, EstadoRepasoAyuda $estado) use (&$cerradas): int {
                $cerradas[] = [$pregunta, $estado];

                return 1;
            },
        );
        $proveedor = $this->createMock(ProveedorRespuestaIA::class);
        $proveedor->expects(self::exactly(3))->method('responder')->willReturnCallback(
            static fn (string $instruccion, string $pregunta): string => match (true) {
                str_contains($pregunta, 'caja') => "Con el concepto 41.\nFUENTES: traspasos",
                str_contains($pregunta, 'traspaso') => "ACLARAR: ¿De banco a caja o al revés?\nFUENTES: traspasos",
                default => 'SIN_RESPUESTA',
            },
        );

        $r = $this->caso($registro, $proveedor)->ejecutar();

        self::assertSame(['¿Cómo paso dinero a la caja?', 'me sale mal el traspaso'], $r['cubiertas']);
        self::assertSame(['¿Cuál es la capital de Francia?'], $r['siguen']);
        self::assertSame(1, $r['no_sirvio']);
        self::assertSame(
            [['¿Cómo paso dinero a la caja?', EstadoRepasoAyuda::Cubierta], ['me sale mal el traspaso', EstadoRepasoAyuda::Cubierta]],
            $cerradas,
        );
    }

    public function testSinProveedorNoTocaNada(): void
    {
        $registro = $this->createMock(RegistroConsultasAyuda::class);
        $registro->method('listarPendientes')->willReturn([$this->pendiente('¿Cómo paso dinero a la caja?')]);
        $registro->expects(self::never())->method('marcarRepasada');

        $r = $this->caso($registro, null)->ejecutar();

        self::assertTrue($r['sin_proveedor']);
        self::assertSame(1, $r['sin_probar']);
    }

    public function testSiLaIaFallaSeParaYLoNoProbadoSiguePendiente(): void
    {
        $registro = $this->createMock(RegistroConsultasAyuda::class);
        $registro->method('listarPendientes')->willReturn([
            $this->pendiente('¿Cómo paso dinero a la caja?'),
            $this->pendiente('¿Y al banco?'),
        ]);
        $registro->expects(self::never())->method('marcarRepasada');
        $proveedor = $this->createMock(ProveedorRespuestaIA::class);
        $proveedor->expects(self::once())->method('responder')->willThrowException(new RuntimeException('sin cuota'));

        $r = $this->caso($registro, $proveedor)->ejecutar();

        self::assertSame('sin cuota', $r['error']);
        self::assertSame(2, $r['sin_probar']);
        self::assertSame([], $r['cubiertas']);
    }

    public function testElTopeLimitaLasConsultasAlModelo(): void
    {
        $registro = $this->createMock(RegistroConsultasAyuda::class);
        $registro->method('listarPendientes')->willReturn([
            $this->pendiente('¿Cómo paso dinero a la caja?'),
            $this->pendiente('¿Y al banco?'),
        ]);
        $proveedor = $this->createMock(ProveedorRespuestaIA::class);
        $proveedor->expects(self::once())->method('responder')->willReturn('SIN_RESPUESTA');

        $r = $this->caso($registro, $proveedor)->ejecutar(1);

        self::assertSame(1, $r['sin_probar']);
    }
}
