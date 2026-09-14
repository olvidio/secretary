<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\application\IniciarSesion;
use src\acceso\application\ResolverPersonaActiva;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;

final class IniciarSesionTest extends TestCase
{
    public function testIdentificadorVacioEsFallo(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $res = (new IniciarSesion($repo, new ResolverPersonaActiva($repo)))->ejecutar('  ', 'x');
        self::assertSame('fallo', $res->estado);
        self::assertFalse($res->ok());
        self::assertFalse($res->desconocido());
    }

    public function testUsuarioInexistenteInvitaARegistrarse(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('porEmailOAlias')->willReturn(null);
        $res = (new IniciarSesion($repo, new ResolverPersonaActiva($repo)))->ejecutar('nadie', 'secret1');
        self::assertSame('desconocido', $res->estado);
        self::assertTrue($res->desconocido());
        self::assertFalse($res->ok());
        self::assertStringContainsString('registr', $res->mensaje);
    }

    public function testClaveIncorrectaNoRevelaSiExiste(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('porEmailOAlias')->willReturn(new Identidad(
            1,
            'scl@x.local',
            password_hash('cambiar', PASSWORD_DEFAULT),
            'Scl',
            true,
            0,
            null,
            null,
            'scl',
        ));
        $res = (new IniciarSesion($repo, new ResolverPersonaActiva($repo)))->ejecutar('scl', 'no-es');
        self::assertSame('fallo', $res->estado);
        self::assertFalse($res->desconocido());
        self::assertSame('Usuario o contraseña incorrectos', $res->mensaje);
    }
}
