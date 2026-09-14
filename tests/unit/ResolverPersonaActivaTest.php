<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\application\ResolverPersonaActiva;
use src\acceso\domain\contracts\IdentidadRepository;

final class ResolverPersonaActivaTest extends TestCase
{
    public function testUnaPersonaSeAsignaAutomaticamente(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('personasDe')->willReturn([42]);
        $res = (new ResolverPersonaActiva($repo))->ejecutar(1, null);
        self::assertSame(42, $res['persona_id']);
        self::assertFalse($res['requiere_elegir']);
    }

    public function testVariasPersonasRequierenElegir(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('personasDe')->willReturn([10, 20]);
        $res = (new ResolverPersonaActiva($repo))->ejecutar(1, null);
        self::assertNull($res['persona_id']);
        self::assertTrue($res['requiere_elegir']);
    }

    public function testSesionValidaSeConserva(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('personasDe')->willReturn([10, 20]);
        $res = (new ResolverPersonaActiva($repo))->ejecutar(1, 20);
        self::assertSame(20, $res['persona_id']);
        self::assertFalse($res['requiere_elegir']);
    }
}
