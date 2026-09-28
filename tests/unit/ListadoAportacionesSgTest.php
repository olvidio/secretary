<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\listados\domain\services\ListadoAportacionesSg;

final class ListadoAportacionesSgTest extends TestCase
{
    public function testAgrupaSySeparaCp(): void
    {
        $r = ListadoAportacionesSg::armar(
            [
                ['id' => 1, 'nombre' => 'B, Ana', 'grupo' => 2, 'clase' => 's'],
                ['id' => 2, 'nombre' => 'A, Luis', 'grupo' => 1, 'clase' => 's'],
                ['id' => 3, 'nombre' => 'C, Pau', 'grupo' => 1, 'clase' => 'cp'],
            ],
            [
                2 => ['11' => [1 => 5000], '12' => [3 => 1000]],
                1 => ['11' => [1 => 2000]],
                3 => ['13' => [2 => 3000]],
            ],
        );
        self::assertSame(1, $r['grupos'][0]['grupo']);
        self::assertSame('A, Luis', $r['grupos'][0]['personas'][0]['nombre']);
        self::assertSame(5000, $r['grupos'][0]['personas'][0]['ordinaria'][0]);
        self::assertSame(1000, $r['grupos'][0]['personas'][0]['extraordinaria'][2]);
        self::assertSame(2, $r['grupos'][1]['grupo']);
        self::assertSame(2000, $r['grupos'][1]['total_ordinaria'][0]);
        self::assertSame('C, Pau', $r['cp'][0]['nombre']);
        self::assertSame(3000, $r['total_cp'][1]);
    }
}
