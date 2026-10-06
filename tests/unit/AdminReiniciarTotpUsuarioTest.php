<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\administracion\application\AdminReiniciarTotpUsuario;

final class AdminReiniciarTotpUsuarioTest extends TestCase
{
    public function testRechazaSinConfirmar(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $this->expectException(InvalidArgumentException::class);
        (new AdminReiniciarTotpUsuario($identidades))->ejecutar(2, 1, false);
    }

    public function testRechazaReinicioPropio(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $this->expectException(InvalidArgumentException::class);
        (new AdminReiniciarTotpUsuario($identidades))->ejecutar(5, 5, true);
    }

    public function testRechazaAdminPlataforma(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $identidades->method('porId')->willReturn(new Identidad(
            1,
            'admin@local',
            'h',
            'Admin',
            true,
            0,
            null,
            null,
            'admin',
            null,
            true,
        ));
        $this->expectException(InvalidArgumentException::class);
        (new AdminReiniciarTotpUsuario($identidades))->ejecutar(1, 99, true);
    }

    public function testReiniciaTotp(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $identidades->method('porId')->willReturn(new Identidad(
            2,
            'u@x.local',
            'h',
            'User',
            true,
            0,
            null,
            null,
            'user',
        ));
        $identidades->expects(self::once())->method('reiniciarTotp')->with(2);

        (new AdminReiniciarTotpUsuario($identidades))->ejecutar(2, 1, true);
    }
}
