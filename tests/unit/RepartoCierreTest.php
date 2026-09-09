<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\cierre\domain\services\RepartoCierre;
use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

final class RepartoCierreTest extends TestCase
{
    public function testExentosYCuota(): void
    {
        $a = new Persona(1, 'Ana', 'A', 'aa', null, null, null, null, null, 1);
        $b = new Persona(2, 'Bea', 'B', 'bb', 1, 12, null, null, null, 2);
        $c = new Persona(3, 'Cal', 'C', 'cc', null, null, null, null, new Dinero('100.00'), 3);
        $r = RepartoCierre::calcular(new Dinero('200.00'), [$a, $b, $c], 6);
        self::assertCount(2, $r);
        self::assertSame('aa', $r[0]['persona']->iniciales);
        self::assertSame('100.00', $r[0]['importe']->toString());
        self::assertSame('cc', $r[1]['persona']->iniciales);
        self::assertSame('100.00', $r[1]['importe']->toString());
    }
}
