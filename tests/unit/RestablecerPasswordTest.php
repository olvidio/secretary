<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\EtiquetaCuentaIdentidad;
use src\acceso\application\RestablecerPassword;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\services\HuellaToken;

final class RestablecerPasswordTest extends TestCase
{
    public function testCuentaMuestraElAlias(): void
    {
        $caso = $this->casoValido('tok', $this->identidad());
        $cuenta = $caso->cuenta('tok');
        self::assertSame('ana', $cuenta['alias']);
        self::assertSame('ana', $cuenta['etiqueta']);
    }

    public function testExitoCambiaLaContrasenaYConsumeElToken(): void
    {
        $repo = $this->repoConToken('tok', $this->identidad());
        $repo->expects(self::once())->method('aplicarPasswordRestablecida')->willReturnCallback(
            static function (int $id, string $hash, string $tokenHash): bool {
                self::assertSame(3, $id);
                self::assertTrue(password_verify('nueva12', $hash));
                self::assertSame(HuellaToken::de('tok'), $tokenHash);

                return true;
            }
        );
        $this->caso($repo)->ejecutar('tok', 'nueva12', 'nueva12');
    }

    public function testTokenDesconocido(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('porTokenRestablecerPassword')->willReturn(null);
        $repo->expects(self::never())->method('aplicarPasswordRestablecida');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El enlace no es válido o ha caducado. Solicite otro.');
        $this->caso($repo)->ejecutar('otro', 'nueva12', 'nueva12');
    }

    public function testTokenCaducado(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('porTokenRestablecerPassword')->willReturn([
            'identidad_id' => 3,
            'expira' => new DateTimeImmutable('-1 minute'),
        ]);
        $repo->expects(self::never())->method('aplicarPasswordRestablecida');
        $this->expectException(InvalidArgumentException::class);
        $this->caso($repo)->cuenta('tok');
    }

    public function testContrasenasDistintasNoConsumenElEnlace(): void
    {
        $repo = $this->repoConToken('tok', $this->identidad());
        $repo->expects(self::never())->method('aplicarPasswordRestablecida');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Las contraseñas nuevas no coinciden');
        $this->caso($repo)->ejecutar('tok', 'nueva12', 'otra12');
    }

    public function testContrasenaCorta(): void
    {
        $repo = $this->repoConToken('tok', $this->identidad());
        $repo->expects(self::never())->method('aplicarPasswordRestablecida');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La contraseña nueva debe tener al menos 6 caracteres');
        $this->caso($repo)->ejecutar('tok', '123', '123');
    }

    public function testSiElTokenYaNoEstaNoCambiaNada(): void
    {
        $repo = $this->repoConToken('tok', $this->identidad());
        $repo->method('aplicarPasswordRestablecida')->willReturn(false);
        $this->expectException(InvalidArgumentException::class);
        $this->caso($repo)->ejecutar('tok', 'nueva12', 'nueva12');
    }

    private function casoValido(string $token, Identidad $identidad): RestablecerPassword
    {
        return $this->caso($this->repoConToken($token, $identidad));
    }

    private function caso(IdentidadRepository $repo): RestablecerPassword
    {
        return new RestablecerPassword($repo, new EtiquetaCuentaIdentidad($repo));
    }

    private function repoConToken(string $token, Identidad $identidad): IdentidadRepository
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('porTokenRestablecerPassword')->willReturnCallback(
            static function (string $hash) use ($token): ?array {
                if ($hash !== HuellaToken::de($token)) {
                    return null;
                }

                return [
                    'identidad_id' => 3,
                    'expira' => new DateTimeImmutable('+30 minutes'),
                ];
            }
        );
        $repo->method('porId')->willReturn($identidad);
        $repo->method('centrosDe')->willReturn([]);
        $repo->method('personasDe')->willReturn([]);

        return $repo;
    }

    private function identidad(): Identidad
    {
        return new Identidad(3, 'ana@x.local', 'vieja', 'Ana', true, 4, new DateTimeImmutable('+10 minutes'), null, 'ana', new DateTimeImmutable('-1 day'));
    }
}
