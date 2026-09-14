<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\personal\domain\services\ClaveAprendizajeBanco;
use src\personal\domain\services\SugeridorCategoriaBanco;

final class SugeridorCategoriaBancoTest extends TestCase
{
    public function testClaveIgnoraNumerosDeTienda(): void
    {
        self::assertSame('caprabo', ClaveAprendizajeBanco::de('CAPRABO 7776'));
        self::assertSame('caprabo', ClaveAprendizajeBanco::de('CAPRABO 7851'));
        self::assertSame('cursor', ClaveAprendizajeBanco::nucleo('CURSOR, AI POWERED IDE'));
        self::assertSame('cursor', ClaveAprendizajeBanco::nucleo('CURSOR USAGE MID JUL'));
    }

    public function testSugierePorBeneficiarioRecienteDelMismoSentido(): void
    {
        $hist = [
            ['concepto' => 'CAPRABO 7776', 'cuenta_id' => 22, 'tipo' => 'gasto'],
            ['concepto' => 'AREA TRUCK', 'cuenta_id' => 21, 'tipo' => 'gasto'],
        ];
        self::assertSame(22, SugeridorCategoriaBanco::sugerir('CAPRABO 7851', 'gasto', $hist));
        self::assertSame(21, SugeridorCategoriaBanco::sugerir('AREA TRUCK', 'gasto', $hist));
        self::assertNull(SugeridorCategoriaBanco::sugerir('CAPRABO 7851', 'ingreso', $hist));
        self::assertNull(SugeridorCategoriaBanco::sugerir('MERCADONA', 'gasto', $hist));
    }

    public function testNucleoUneComerciosDelMismoGrupo(): void
    {
        $hist = [
            ['concepto' => 'CURSOR, AI POWERED IDE', 'cuenta_id' => 79, 'tipo' => 'gasto'],
        ];
        self::assertSame(79, SugeridorCategoriaBanco::sugerir('CURSOR USAGE MID JUL', 'gasto', $hist));
    }
}
