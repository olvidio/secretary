<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\arqueo\domain\services\DetectarCapuchinos;

final class DetectarCapuchinosTest extends TestCase
{
    public function testDiferenciaMultiploDeNueve(): void
    {
        $d = new DetectarCapuchinos();
        self::assertTrue($d->diferenciaCandidata(900));
        self::assertTrue($d->diferenciaCandidata(90));
        self::assertTrue($d->diferenciaCandidata(-1800));
        self::assertFalse($d->diferenciaCandidata(0));
        self::assertFalse($d->diferenciaCandidata(100));
    }

    public function testDetectaCifrasContiguasInvertidas(): void
    {
        $d = new DetectarCapuchinos();
        $alts = $d->alternativasQueExplican(4500, 900);
        self::assertContains(5400, $alts);
    }

    public function testDetectaCifrasNoContiguas(): void
    {
        $d = new DetectarCapuchinos();
        $alts = $d->alternativasQueExplican(7200, 4500);
        self::assertContains(2700, $alts);
    }

    public function testDetectaCorrimientoDeComa(): void
    {
        $d = new DetectarCapuchinos();
        $alts = $d->alternativasQueExplican(1200, 1080);
        self::assertContains(120, $alts);
    }

    public function testNoProponeSiLaDiferenciaNoEsMultiploDeNueve(): void
    {
        $d = new DetectarCapuchinos();
        self::assertSame([], $d->alternativasQueExplican(4500, 100));
    }
}
