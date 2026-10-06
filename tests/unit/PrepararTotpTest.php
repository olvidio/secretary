<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\application\PrepararTotp;
use src\acceso\domain\contracts\CifradorSecretos;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\entity\VinculoCentro;

final class PrepararTotpTest extends TestCase
{
    public function testUriTotpUsaNombreDeIdentidadNoDelCentro(): void
    {
        $identidad = new Identidad(
            7,
            'maria@example.test',
            'hash',
            'María García',
            true,
            0,
            null,
            null,
            'maria',
        );
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('porId')->willReturn($identidad);
        $repo->method('totpConfirmado')->willReturn(false);
        $repo->method('centrosDe')->willReturn([
            new VinculoCentro(1, 'admin', 'n1', 'Centro Norte'),
            new VinculoCentro(2, 'admin', 'sg1', 'Centro SG'),
        ]);
        $cifrador = $this->createStub(CifradorSecretos::class);
        $cifrador->method('cifrar')->willReturn('cifrado');

        $datos = (new PrepararTotp($repo, $cifrador))->ejecutar(7);

        self::assertStringContainsString(rawurlencode('María García - maria@example.test'), $datos['uri']);
        self::assertStringNotContainsString('Centro', $datos['uri']);
    }
}
