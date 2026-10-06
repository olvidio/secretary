<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\administracion\application\AdminDesvincularPersonaUsuario;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
use src\personas\application\DesvincularPersonaCentro;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;

final class AdminDesvincularPersonaUsuarioTest extends TestCase
{
    public function testRechazaLibroPersonal(): void
    {
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('porId')->willReturn(new Identidad(
            2,
            'p@x.local',
            'h',
            'Per',
            true,
            0,
            null,
            null,
            'per',
        ));
        $personas = $this->createStub(PersonaRepository::class);
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
            99,
            true,
            null,
            false,
        ));
        $centros = $this->createStub(CentroRepository::class);
        $centros->method('porId')->willReturn(new Centro(99, 'lib', 'Libro', 'p', 'vivienda', 'H16n', true));

        $this->expectException(InvalidArgumentException::class);
        (new AdminDesvincularPersonaUsuario(
            $identidades,
            $personas,
            $centros,
            new DesvincularPersonaCentro(
                $this->createStub(IdentidadRepository::class),
                $this->createStub(PersonaRepository::class),
            ),
        ))->ejecutar(2, 10, 1);
    }

    public function testDesvinculaPersonaEnCentroReal(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $identidades->method('porId')->willReturn(new Identidad(
            2,
            'p@x.local',
            'h',
            'Per',
            true,
            0,
            null,
            null,
            'per',
        ));
        $identidades->method('personasDe')->willReturn([10]);
        $identidades->expects(self::once())->method('desvincularPersona')->with(10);
        $personas = $this->createMock(PersonaRepository::class);
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
            null,
            false,
        ));
        $centros = $this->createStub(CentroRepository::class);
        $centros->method('porId')->willReturn(new Centro(3, 'H16', 'Home', 'n', 'vivienda', 'H16n', true));
        $desvincular = new DesvincularPersonaCentro($identidades, $personas);

        (new AdminDesvincularPersonaUsuario($identidades, $personas, $centros, $desvincular))
            ->ejecutar(2, 10, 1);
    }
}
