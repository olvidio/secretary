<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\AplicarEmailIdentidad;
use src\acceso\application\GuardarEmailUsuario;
use src\acceso\application\NotificarCambioEmailUsuario;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\personas\domain\contracts\PersonaRepository;
use src\shared\domain\contracts\EnviadorCorreo;

final class GuardarEmailUsuarioTest extends TestCase
{
    public function testRechazaCorreoInvalido(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso()->ejecutar(1, 'no-es-correo');
    }

    public function testRechazaCorreoDeOtraCuentaPersonal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $otra = new Identidad(2, 'otro@x.local', 'h', 'Otro', true, 0, null, null, 'otro');
        $this->caso($otra, true)->ejecutar(1, 'otro@x.local');
    }

    public function testPideConfirmacionSinCambiarEmailActual(): void
    {
        putenv('REGISTRO_AUTO_CONFIRMA_EMAIL=0');
        $repo = $this->createMock(IdentidadRepository::class);
        $personas = $this->createStub(PersonaRepository::class);
        $correo = $this->createMock(EnviadorCorreo::class);
        $mia = new Identidad(1, 'viejo@x.local', 'h', 'Scl', true, 0, null, null, 'scl', new \DateTimeImmutable());
        $repo->method('porId')->willReturn($mia);
        $repo->method('esCuentaPersonal')->willReturn(true);
        $repo->method('cuentaPersonalPorEmail')->willReturn(null);
        $repo->method('emailPendienteDe')->willReturn('nuevo@x.local');
        $repo->expects(self::once())->method('guardarCambioEmailPendiente')->with(1, 'nuevo@x.local', self::isType('string'), self::isInstanceOf(\DateTimeImmutable::class));
        $repo->expects(self::never())->method('guardar');
        $correo->expects(self::once())->method('enviar')->with('nuevo@x.local', self::anything(), self::anything());
        $out = new GuardarEmailUsuario(
            $repo,
            new AplicarEmailIdentidad($repo, $personas),
            new NotificarCambioEmailUsuario($repo, $correo),
        )->ejecutar(1, 'Nuevo@x.local');
        self::assertSame('viejo@x.local', $out['email']);
        self::assertTrue($out['pendiente_confirmacion']);
        putenv('REGISTRO_AUTO_CONFIRMA_EMAIL');
    }

    private function caso(?Identidad $conflictoPersonal = null, bool $esPersonal = true): GuardarEmailUsuario
    {
        putenv('REGISTRO_AUTO_CONFIRMA_EMAIL=1');
        $repo = $this->createStub(IdentidadRepository::class);
        $mia = new Identidad(1, 'viejo@x.local', 'h', 'Scl', true, 0, null, null, 'scl');
        $repo->method('porId')->willReturn($mia);
        $repo->method('esCuentaPersonal')->willReturn($esPersonal);
        $repo->method('cuentaPersonalPorEmail')->willReturn($conflictoPersonal);
        $repo->method('personasDe')->willReturn([]);
        $repo->method('guardar')->willReturnArgument(0);
        $personas = $this->createStub(PersonaRepository::class);
        $correo = $this->createStub(EnviadorCorreo::class);

        return new GuardarEmailUsuario(
            $repo,
            new AplicarEmailIdentidad($repo, $personas),
            new NotificarCambioEmailUsuario($repo, $correo),
        );
    }
}
