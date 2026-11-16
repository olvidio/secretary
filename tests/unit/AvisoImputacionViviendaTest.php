<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\apuntes\domain\services\AvisoImputacionVivienda;

final class AvisoImputacionViviendaTest extends TestCase
{
    public function testP211ConImputacionDistintaRecuerdaElG11(): void
    {
        $avisos = AvisoImputacionVivienda::avisos('P', '211', '2026-11-04', '2026-10-31', false);

        self::assertCount(1, $avisos);
        self::assertStringContainsString('G/11', $avisos[0]);
        self::assertStringContainsString('31/10/2026', $avisos[0]);
    }

    public function testG14RecuerdaElP6(): void
    {
        self::assertStringContainsString('P/6', AvisoImputacionVivienda::avisos('g', '14', '2026-11-04', '2026-10-31', false)[0]);
    }

    public function testSinAvisoSiNoHayImputacionDistintaOEsOtroConcepto(): void
    {
        self::assertSame([], AvisoImputacionVivienda::avisos('P', '211', '2026-11-04', '', false));
        self::assertSame([], AvisoImputacionVivienda::avisos('P', '211', '2026-11-04', '2026-11-04', false));
        self::assertSame([], AvisoImputacionVivienda::avisos('P', '22', '2026-11-04', '2026-10-31', false));
    }

    public function testConContrapartidasNoAvisaPorqueLaCadenaYaVaJunta(): void
    {
        self::assertSame([], AvisoImputacionVivienda::avisos('P', '211', '2026-11-04', '2026-10-31', true));
    }
}
