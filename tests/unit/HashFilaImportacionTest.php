<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\apuntes\domain\entity\Apunte;
use src\importacion\domain\services\HashFilaImportacion;
use src\shared\domain\value_objects\Dinero;

final class HashFilaImportacionTest extends TestCase
{
    public function testMismoContenidoMismoHashYCambioDeImporteDistinto(): void
    {
        $base = new Apunte(
            null,
            new DateTimeImmutable('2026-03-01'),
            'G',
            'C',
            null,
            '201',
            'luz',
            new Dinero('10.00'),
        );
        $igual = new Apunte(
            99,
            new DateTimeImmutable('2026-03-01'),
            'G',
            'C',
            null,
            '201',
            'luz',
            new Dinero('10.00'),
        );
        $otro = new Apunte(
            null,
            new DateTimeImmutable('2026-03-01'),
            'G',
            'C',
            null,
            '201',
            'luz',
            new Dinero('11.00'),
        );

        self::assertSame(HashFilaImportacion::de($base), HashFilaImportacion::de($igual));
        self::assertNotSame(HashFilaImportacion::de($base), HashFilaImportacion::de($otro));
    }
}
