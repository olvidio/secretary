<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\value_objects\RolCentro;

final class RolCentroTest extends TestCase
{
    public function testAsignaAdminYConsulta(): void
    {
        self::assertSame('admin', RolCentro::exigirAsignable(' admin '));
        self::assertSame('consulta', RolCentro::exigirAsignable('CONSULTA'));
    }

    public function testRechazaUnRolDesconocido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RolCentro::exigirAsignable('operador');
    }

    public function testOperadorAntiguoSiguePudiendoEscribir(): void
    {
        self::assertTrue(RolCentro::puedeEscribir('operador'));
        self::assertTrue(RolCentro::puedeEscribir('admin'));
        self::assertFalse(RolCentro::puedeEscribir('consulta'));
        self::assertTrue(RolCentro::esConsulta(' consulta '));
    }
}
