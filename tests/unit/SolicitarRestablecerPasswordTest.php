<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use src\acceso\application\EtiquetaCuentaIdentidad;
use src\acceso\application\NotificarRestablecerPassword;
use src\acceso\application\SolicitarRestablecerPassword;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\services\HuellaToken;
use src\shared\domain\contracts\EnviadorCorreo;

final class SolicitarRestablecerPasswordTest extends TestCase
{
    public function testIdentificadorVacio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Indique el alias o el correo');
        $this->caso(null)->ejecutar('   ');
    }

    public function testAliasDesconocidoNoEnvia(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('porAlias')->willReturn(null);
        $repo->expects(self::never())->method('guardarTokenRestablecerPassword');
        $correo = $this->createMock(EnviadorCorreo::class);
        $correo->expects(self::never())->method('enviar');
        $this->casoCon($repo, $correo)->ejecutar('nadie');
    }

    public function testAliasGuardaHuellaYEnviaUnEnlace(): void
    {
        $identidad = $this->identidad(7, 'ana@x.local', 'ana');
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('porAlias')->willReturn($identidad);
        $repo->method('centrosDe')->willReturn([]);
        $repo->method('personasDe')->willReturn([]);
        $hashGuardado = null;
        $repo->expects(self::once())->method('guardarTokenRestablecerPassword')->willReturnCallback(
            static function (int $id, string $hash, DateTimeImmutable $expira) use (&$hashGuardado): void {
                self::assertSame(7, $id);
                self::assertSame(64, strlen($hash));
                $diff = $expira->getTimestamp() - time();
                self::assertGreaterThan(7000, $diff);
                self::assertLessThan(7300, $diff);
                $hashGuardado = $hash;
            }
        );
        $correo = $this->createMock(EnviadorCorreo::class);
        $correo->expects(self::once())->method('enviar')->willReturnCallback(
            static function (string $dest, string $asunto, string $cuerpo) use (&$hashGuardado): void {
                self::assertSame('ana@x.local', $dest);
                self::assertSame('Nueva contraseña en Secretario', $asunto);
                self::assertSame(1, preg_match('#/restablecer-contrasena\?token=([0-9a-f]{64})#', $cuerpo, $m));
                self::assertSame(HuellaToken::de($m[1]), $hashGuardado);
                self::assertStringContainsString('ana', $cuerpo);
            }
        );
        $this->casoCon($repo, $correo)->ejecutar('Ana');
    }

    public function testMismoCorreoUnMensajeConDosEnlaces(): void
    {
        $una = $this->identidad(1, 'ana@x.local', 'personal');
        $otra = $this->identidad(2, 'ana@x.local', 'centro');
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('listarPorEmail')->willReturn([$una, $otra]);
        $repo->method('centrosDe')->willReturn([]);
        $repo->method('personasDe')->willReturn([]);
        $repo->expects(self::exactly(2))->method('guardarTokenRestablecerPassword');
        $correo = $this->createMock(EnviadorCorreo::class);
        $correo->expects(self::once())->method('enviar')->willReturnCallback(
            static function (string $dest, string $asunto, string $cuerpo): void {
                self::assertSame('ana@x.local', $dest);
                self::assertSame(2, preg_match_all('#/restablecer-contrasena\?token=#', $cuerpo));
                self::assertStringContainsString('personal', $cuerpo);
                self::assertStringContainsString('centro', $cuerpo);
            }
        );
        $this->casoCon($repo, $correo)->ejecutar('ANA@x.local');
    }

    public function testOmiteInactivaYSinCorreoConfirmado(): void
    {
        $inactiva = $this->identidad(1, 'a@x.local', 'off', false);
        $sinCorreo = new Identidad(2, 'a@x.local', 'h', 'A', true, 0, null, null, 'nueva', null);
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('listarPorEmail')->willReturn([$inactiva, $sinCorreo]);
        $repo->expects(self::never())->method('guardarTokenRestablecerPassword');
        $correo = $this->createMock(EnviadorCorreo::class);
        $correo->expects(self::never())->method('enviar');
        $this->casoCon($repo, $correo)->ejecutar('a@x.local');
    }

    public function testElFalloDelCorreoSeVe(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('porAlias')->willReturn($this->identidad(3, 'ana@x.local', 'ana'));
        $repo->method('centrosDe')->willReturn([]);
        $repo->method('personasDe')->willReturn([]);
        $correo = $this->createMock(EnviadorCorreo::class);
        $correo->method('enviar')->willThrowException(new RuntimeException('smtp caído'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('smtp caído');
        $this->casoCon($repo, $correo)->ejecutar('ana');
    }

    private function caso(?Identidad $identidad): SolicitarRestablecerPassword
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('porAlias')->willReturn($identidad);

        return $this->casoCon($repo, $this->createMock(EnviadorCorreo::class));
    }

    private function casoCon(IdentidadRepository $repo, EnviadorCorreo $correo): SolicitarRestablecerPassword
    {
        return new SolicitarRestablecerPassword(
            $repo,
            new NotificarRestablecerPassword($correo),
            new EtiquetaCuentaIdentidad($repo),
        );
    }

    private function identidad(int $id, string $email, string $alias, bool $activo = true): Identidad
    {
        return new Identidad(
            $id,
            $email,
            'hash',
            'Ana',
            $activo,
            2,
            new DateTimeImmutable('+1 hour'),
            null,
            $alias,
            new DateTimeImmutable('-1 day'),
        );
    }
}
