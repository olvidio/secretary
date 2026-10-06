<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\entity\VinculoCentro;
use src\administracion\application\AdminDesvincularCentroUsuario;

final class AdminDesvincularCentroUsuarioTest extends TestCase
{
    public function testRechazaSiEsUnicoSecretario(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $identidades->method('porId')->willReturn(new Identidad(
            2,
            's@x.local',
            'h',
            'Sec',
            true,
            0,
            null,
            null,
            'sec',
        ));
        $identidades->method('bajaCentroPendiente')->willReturn(null);
        $identidades->method('centrosDe')->willReturn([
            new VinculoCentro(5, 'admin', 'H16', 'Home'),
        ]);
        $identidades->method('contarSecretariosDeCentro')->with(5)->willReturn(1);

        $this->expectException(InvalidArgumentException::class);
        (new AdminDesvincularCentroUsuario($identidades))->ejecutar(2, 5, 1);
    }

    public function testDesvinculaCentro(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $identidades->method('porId')->willReturn(new Identidad(
            2,
            's@x.local',
            'h',
            'Sec',
            true,
            0,
            null,
            null,
            'sec',
        ));
        $identidades->method('bajaCentroPendiente')->willReturn(null);
        $identidades->method('centrosDe')->willReturn([
            new VinculoCentro(5, 'admin', 'H16', 'Home'),
        ]);
        $identidades->method('contarSecretariosDeCentro')->with(5)->willReturn(2);
        $identidades->expects(self::once())->method('desvincularCentro')->with(2, 5);

        (new AdminDesvincularCentroUsuario($identidades))->ejecutar(2, 5, 1);
    }
}
