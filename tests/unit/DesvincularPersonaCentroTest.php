<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\personas\application\DesvincularPersonaCentro;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;

final class DesvincularPersonaCentroTest extends TestCase
{
    public function testRechazaSiNoEsSuPersona(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('personasDe')->willReturn([99]);
        (new DesvincularPersonaCentro($identidades, $this->createStub(PersonaRepository::class)))
            ->ejecutar(1, 10);
    }

    public function testDesvinculaYLimpiaEmailCoincidente(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $personas = $this->createMock(PersonaRepository::class);
        $identidades->method('personasDe')->willReturn([10]);
        $identidades->method('porId')->willReturn(new Identidad(1, 'dani@x.local', 'h', 'Dani', true, 0, null, null, 'dani'));
        $personas->method('porId')->willReturn(new Persona(
            10,
            'Dani',
            '',
            'dani',
            null,
            null,
            null,
            null,
            null,
            0,
            3,
            true,
            'dani@x.local',
            false,
        ));
        $personas->expects(self::once())->method('guardarEmail')->with(10, null);
        $identidades->expects(self::once())->method('desvincularPersona')->with(10);

        (new DesvincularPersonaCentro($identidades, $personas))->ejecutar(1, 10);
    }
}
