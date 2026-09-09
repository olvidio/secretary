<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\personal\domain\services\CatalogoMaestroPersonal;

final class CatalogoMaestroPersonalTest extends TestCase
{
    public function testIncluyePlanPYExcluyeElSaldo9(): void
    {
        $codigos = array_map(static fn (array $c): string => $c['codigo'], CatalogoMaestroPersonal::cuentas());
        self::assertContains('111', $codigos);
        self::assertContains('21', $codigos);
        self::assertContains('79', $codigos);
        self::assertNotContains('9', $codigos);
        self::assertTrue(CatalogoMaestroPersonal::existe('21'));
        self::assertFalse(CatalogoMaestroPersonal::existe('9'));
        self::assertFalse(CatalogoMaestroPersonal::existe('inventado'));
        self::assertCount(25, $codigos);
    }
}
