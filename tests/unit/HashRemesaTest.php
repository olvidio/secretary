<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\remesas\domain\entity\RemesaLinea;
use src\remesas\domain\services\HashRemesa;

final class HashRemesaTest extends TestCase
{
    public function testElOrdenDeLasLineasNoCambiaElHash(): void
    {
        $a = new RemesaLinea(null, null, '22', 1250, []);
        $b = new RemesaLinea(null, null, '111', 5000, []);
        self::assertSame(HashRemesa::deLineas([$a, $b]), HashRemesa::deLineas([$b, $a]));
    }

    public function testUnImporteDistintoCambiaElHash(): void
    {
        $a = new RemesaLinea(null, null, '22', 1250, []);
        $b = new RemesaLinea(null, null, '22', 2250, []);
        self::assertNotSame(HashRemesa::deLineas([$a]), HashRemesa::deLineas([$b]));
    }
}
