<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\apuntes\domain\entity\Apunte;
use src\apuntes\domain\services\ClasificarConceptoViviendaP;
use src\shared\domain\value_objects\Dinero;

final class ClasificarConceptoViviendaPTest extends TestCase
{
    public function testP21ConParG11PasaA211(): void
    {
        $fecha = new DateTimeImmutable('2026-02-28');
        $apuntes = [
            $this->apunte('P', '21', 'qr', '650.00', $fecha),
            $this->apunte('G', '11', 'qr', '650.00', $fecha),
            $this->apunte('P', '21', 'ab', '100.00', $fecha),
        ];
        $out = (new ClasificarConceptoViviendaP())->reclasificarApuntes($apuntes);
        self::assertSame('211', $out[0]->conceptoCodigo);
        self::assertSame('212', $out[2]->conceptoCodigo);
    }

    private function apunte(string $cuenta, string $concepto, string $ini, string $cant, DateTimeImmutable $fecha): Apunte
    {
        return new Apunte(null, $fecha, $cuenta, 'A', $ini, $concepto, null, new Dinero($cant));
    }
}
