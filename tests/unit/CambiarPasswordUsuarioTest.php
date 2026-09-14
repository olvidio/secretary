<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\CambiarPasswordUsuario;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;

final class CambiarPasswordUsuarioTest extends TestCase
{
    public function testRechazaActualIncorrecta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $hash = password_hash('buena', PASSWORD_DEFAULT);
        $this->caso($hash)->ejecutar(1, 'mala', 'nueva12', 'nueva12');
    }

    public function testRechazaConfirmacionDistinta(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $hash = password_hash('actual', PASSWORD_DEFAULT);
        $this->caso($hash)->ejecutar(1, 'actual', 'nueva12', 'otra12');
    }

    public function testCambiaPassword(): void
    {
        $hash = password_hash('actual', PASSWORD_DEFAULT);
        $repo = $this->createMock(IdentidadRepository::class);
        $mia = new Identidad(1, 'a@x.local', $hash, 'A', true, 0, null, null, 'a');
        $repo->method('porId')->willReturn($mia);
        $repo->expects(self::once())->method('guardar')->willReturnCallback(
            static function (Identidad $i) use ($mia): Identidad {
                self::assertSame(1, $i->id);
                self::assertTrue(password_verify('nueva12', $i->passwordHash));

                return $mia;
            }
        );
        (new CambiarPasswordUsuario($repo))->ejecutar(1, 'actual', 'nueva12', 'nueva12');
    }

    private function caso(string $hash): CambiarPasswordUsuario
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('porId')->willReturn(new Identidad(1, 'a@x.local', $hash, 'A', true, 0, null, null, 'a'));

        return new CambiarPasswordUsuario($repo);
    }
}
