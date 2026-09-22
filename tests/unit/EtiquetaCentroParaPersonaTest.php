<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\entity\Centro;
use src\personas\application\EtiquetaCentroParaPersona;

final class EtiquetaCentroParaPersonaTest extends TestCase
{
    public function testUsaNombreDelCentroCuandoNoEsElDelSecretario(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('usuariosDeCentro')->willReturn([
            ['nombre' => 'Secretario', 'alias' => 'sec', 'email' => 's@x.local', 'rol' => 'admin'],
        ]);
        $centro = new Centro(1, 'casa-a', 'Casa San José', 'n', 'vivienda', 'H16n');

        self::assertSame('Casa San José', (new EtiquetaCentroParaPersona($repo))->ejecutar($centro));
    }

    public function testSiNombreCoincideConSecretarioDerivaDelCodigo(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('usuariosDeCentro')->willReturn([
            ['nombre' => 'Ana Sec', 'alias' => 'sec-ana', 'email' => 'a@x.local', 'rol' => 'admin'],
        ]);
        $centro = new Centro(2, 'casa-a', 'Ana Sec', 'n', 'vivienda', 'H16n');

        self::assertSame('Casa A', (new EtiquetaCentroParaPersona($repo))->ejecutar($centro));
    }
}
