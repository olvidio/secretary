<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\RegistrarUsuario;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\personas\domain\contracts\PersonaRepository;

final class RegistrarUsuarioTest extends TestCase
{
    public function testRechazaAliasInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso()->ejecutar('1malo', 'a@x.local', 'secret1', 'secret1');
    }

    public function testRechazaCorreoInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso()->ejecutar('nuevo', 'no-es-correo', 'secret1', 'secret1');
    }

    public function testRechazaSiLasClavesNoCoinciden(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso()->ejecutar('nuevo', 'a@x.local', 'secret1', 'secret2');
    }

    public function testRechazaSiYaExisteElUsuario(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $existente = new Identidad(1, 'viejo@x.local', 'h', 'Viejo', true, 0, null, null, 'nuevo');
        $this->caso($existente)->ejecutar('nuevo', 'a@x.local', 'secret1', 'secret1');
    }

    public function testCreaIdentidadSinCentro(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $personas = $this->createStub(PersonaRepository::class);

        $identidades->method('porEmailOAlias')->willReturn(null);
        $personas->method('porEmail')->willReturn(null);
        $identidades->expects(self::never())->method('vincularPersona');
        $identidades->method('guardar')->willReturnCallback(
            static function (Identidad $i): Identidad {
                return new Identidad(5, $i->email, $i->passwordHash, $i->nombre, true, 0, null, null, $i->alias);
            }
        );
        $identidades->expects(self::once())->method('guardarVerificacionEmail')->with(5, self::isType('string'), self::isInstanceOf(\DateTimeImmutable::class));
        $identidades->method('porId')->willReturnCallback(
            static fn (int $id): ?Identidad => $id === 5
                ? new Identidad(5, 'dani@x.local', 'h', 'Dani', true, 0, null, null, 'dani', null)
                : null,
        );

        $out = new RegistrarUsuario($identidades, $personas)
            ->ejecutar('dani', 'dani@x.local', 'secret1', 'secret1', 'Dani', true);
        self::assertSame(5, $out['identidad']->id);
        self::assertSame('dani', $out['identidad']->alias);
        self::assertNotSame('', $out['token_verificacion']);
    }

    public function testRechazaSiNoAceptaCondiciones(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Condiciones de uso');
        $this->caso()->ejecutar('dani', 'dani@x.local', 'secret1', 'secret1', 'Dani', false);
    }

    private function caso(?Identidad $existente = null): RegistrarUsuario
    {
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('porEmailOAlias')->willReturn($existente);
        $personas = $this->createStub(PersonaRepository::class);

        return new RegistrarUsuario($identidades, $personas);
    }
}
