<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\shared\infrastructure\persistence\RutasCopiasSeguridad;

final class RutasCopiasSeguridadTest extends TestCase
{
    public function testDirectorioPorDefectoApuntaAlProyecto(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertSame($root . '/var/backups', RutasCopiasSeguridad::directorio());
    }
}
