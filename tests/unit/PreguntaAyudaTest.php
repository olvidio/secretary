<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\ayuda\domain\value_objects\PreguntaAyuda;

final class PreguntaAyudaTest extends TestCase
{
    public function testColapsaEspaciosYSaltos(): void
    {
        $pregunta = new PreguntaAyuda("  ¿Cómo   anoto\n un traspaso?  ");

        self::assertSame('¿Cómo anoto un traspaso?', $pregunta->texto);
    }

    public function testRechazaPreguntaDemasiadoCorta(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PreguntaAyuda('¿eh?');
    }

    public function testRechazaPreguntaDemasiadoLarga(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PreguntaAyuda(str_repeat('a', PreguntaAyuda::MAXIMO + 1));
    }

    public function testLaHuellaIgnoraMayusculasYSignos(): void
    {
        $a = new PreguntaAyuda('¿Cómo anoto un traspaso?');
        $b = new PreguntaAyuda('como anoto un traspaso');

        self::assertSame($a->huella('v1'), $b->huella('v1'));
    }

    public function testLaHuellaCambiaAlCambiarElManual(): void
    {
        $pregunta = new PreguntaAyuda('¿Cómo anoto un traspaso?');

        self::assertNotSame($pregunta->huella('v1'), $pregunta->huella('v2'));
    }

    public function testPreguntasDistintasTienenHuellasDistintas(): void
    {
        $a = new PreguntaAyuda('¿Cómo anoto un traspaso?');
        $b = new PreguntaAyuda('¿Cómo cierro el mes?');

        self::assertNotSame($a->huella('v1'), $b->huella('v1'));
    }
}
