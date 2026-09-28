<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\informes\domain\services\Estadistica613Sg;
use src\shared\domain\value_objects\Dinero;

final class Estadistica613SgTest extends TestCase
{
    public function testCuadraConLaHojaDelCentro(): void
    {
        $r = Estadistica613Sg::armar(
            25,
            26,
            166,
            5,
            new Dinero('19500.00'),
            new Dinero('13138.80'),
            8,
        );

        $this->assertSame(25, $r['num_s_previsto']);
        $this->assertSame(26, $r['num_s']);
        $this->assertSame(200, $r['aportaciones_previsto']);
        $this->assertSame(166, $r['aportaciones']);
        $this->assertEqualsWithDelta(0.83, $r['aportaciones_pct'], 0.0001);
        $this->assertSame('65.00', $r['media_prevista']);
        $this->assertSame('79.15', $r['media']);
        $this->assertSame(5, $r['sin_aportacion']);
    }
}
