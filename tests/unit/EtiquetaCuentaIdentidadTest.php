<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\application\EtiquetaCuentaIdentidad;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\entity\VinculoCentro;

final class EtiquetaCuentaIdentidadTest extends TestCase
{
    public function testEtiquetaLibroPersonal(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $id = new Identidad(3, 'a@x.local', 'h', 'Ana', true, 0, null, null, 'ana');
        $repo->method('centrosDe')->willReturn([]);
        $repo->method('personasDe')->willReturn([7]);

        $etiqueta = (new EtiquetaCuentaIdentidad($repo))->ejecutar($id);
        self::assertStringContainsString('ana', $etiqueta);
    }

    public function testEtiquetaSecretarioDeCentro(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $id = new Identidad(4, 's@x.local', 'h', 'Sec', true, 0, null, null, 'sec-a');
        $repo->method('centrosDe')->willReturn([
            new VinculoCentro(10, 'admin', 'casa', 'Mi Casa'),
        ]);
        $repo->method('personasDe')->willReturn([]);

        $etiqueta = (new EtiquetaCuentaIdentidad($repo))->ejecutar($id);
        self::assertStringContainsString('Mi Casa', $etiqueta);
        self::assertStringContainsString('sec-a', $etiqueta);
    }
}
