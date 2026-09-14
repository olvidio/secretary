<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\GuardarEmailUsuario;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\personas\domain\contracts\PersonaRepository;

final class GuardarEmailUsuarioTest extends TestCase
{
    public function testRechazaCorreoInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso()->ejecutar(1, 'no-es-correo');
    }

    public function testRechazaCorreoDeOtraCuenta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $otra = new Identidad(2, 'otro@x.local', 'h', 'Otro', true, 0, null, null, 'otro');
        $this->caso($otra)->ejecutar(1, 'otro@x.local');
    }

    public function testGuardaYSincronizaPersona(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $personas = $this->createMock(PersonaRepository::class);
        $mia = new Identidad(1, 'viejo@x.local', 'h', 'Scl', true, 0, null, null, 'scl');
        $repo->method('porId')->willReturn($mia);
        $repo->method('porEmailOAlias')->willReturn($mia);
        $repo->method('personasDe')->willReturn([9]);
        $repo->expects(self::once())->method('guardar')->willReturn($mia);
        $personas->expects(self::once())->method('guardarEmail')->with(9, 'nuevo@x.local');
        $out = (new GuardarEmailUsuario($repo, $personas))->ejecutar(1, 'Nuevo@x.local');
        self::assertSame('nuevo@x.local', $out);
    }

    private function caso(?Identidad $conflicto = null): GuardarEmailUsuario
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $mia = new Identidad(1, 'viejo@x.local', 'h', 'Scl', true, 0, null, null, 'scl');
        $repo->method('porId')->willReturn($mia);
        $repo->method('porEmailOAlias')->willReturn($conflicto ?? $mia);
        $personas = $this->createStub(PersonaRepository::class);

        return new GuardarEmailUsuario($repo, $personas);
    }
}
