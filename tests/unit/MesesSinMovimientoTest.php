<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\informes\domain\services\MesesSinMovimiento;

final class MesesSinMovimientoTest extends TestCase
{
    public function testDetectaMesesSinApunteSalvoExencion(): void
    {
        $r = (new MesesSinMovimiento())->ejecutar(
            [
                ['iniciales' => 'aa', 'nombre' => 'Ana', 'exento_meses' => []],
                ['iniciales' => 'bb', 'nombre' => 'Bea', 'exento_meses' => [2]],
                ['iniciales' => 'cc', 'nombre' => 'Cal', 'exento_meses' => [1, 2, 3]],
            ],
            [
                ['iniciales' => 'aa', 'fecha' => '2026-01-10', 'concepto_codigo' => '111', 'es_cierre' => false],
                ['iniciales' => 'bb', 'fecha' => '2026-01-05', 'concepto_codigo' => '22', 'es_cierre' => false],
                ['iniciales' => 'aa', 'fecha' => '2026-03-01', 'concepto_codigo' => '21', 'es_cierre' => true],
                ['iniciales' => 'aa', 'fecha' => '2026-02-01', 'concepto_codigo' => '32', 'es_cierre' => false],
            ],
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-03-15'),
        );

        self::assertFalse($r['ok']);
        self::assertSame(['2026-01', '2026-02', '2026-03'], $r['meses']);
        self::assertCount(2, $r['por_persona']);
        self::assertSame('aa', $r['por_persona'][0]['iniciales']);
        self::assertSame(['febrero', 'marzo'], $r['por_persona'][0]['meses_es']);
        self::assertSame('bb', $r['por_persona'][1]['iniciales']);
        self::assertSame(['marzo'], $r['por_persona'][1]['meses_es']);
    }

    public function testOkSiTodosTienenMovimientoEnMesesExigidos(): void
    {
        $r = (new MesesSinMovimiento())->ejecutar(
            [
                ['iniciales' => 'aa', 'nombre' => 'Ana', 'exento_meses' => [1]],
            ],
            [
                ['iniciales' => 'aa', 'fecha' => '2026-02-20', 'concepto_codigo' => '111'],
            ],
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-02-28'),
        );

        self::assertTrue($r['ok']);
        self::assertSame([], $r['por_persona']);
    }
}
