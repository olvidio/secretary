<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\CambiarTipoUsuario;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\VinculoCentro;

final class CambiarTipoUsuarioTest extends TestCase
{
    public function testSecretarioSinPersonaNoPasaANivel1(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso(true, [new VinculoCentro(3, 'admin', 'mt', 'Montagut')], [])
            ->ejecutar(1, 'persona');
    }

    public function testPersonaSinCentroNoPasaANivel2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->caso(false, [], [9])->ejecutar(2, 'centro');
    }

    public function testConAmbosVínculosCambiaAPersona(): void
    {
        $out = $this->caso(
            true,
            [new VinculoCentro(3, 'admin', 'mt', 'Montagut')],
            [9],
        )->ejecutar(1, 'persona');
        self::assertSame('persona', $out['nivel']);
        self::assertSame(9, $out['persona_id']);
        self::assertSame('/yo', $out['siguiente']);
    }

    public function testConAmbosVínculosCambiaACentro(): void
    {
        $out = $this->caso(
            true,
            [new VinculoCentro(3, 'admin', 'mt', 'Montagut')],
            [9],
        )->ejecutar(1, 'centro');
        self::assertSame('centro', $out['nivel']);
        self::assertNull($out['persona_id']);
        self::assertSame('/', $out['siguiente']);
    }

    /**
     * @param list<VinculoCentro> $centros
     * @param list<int> $personas
     */
    private function caso(bool $totp, array $centros, array $personas): CambiarTipoUsuario
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('centrosDe')->willReturn($centros);
        $repo->method('personasDe')->willReturn($personas);
        $repo->method('totpConfirmado')->willReturn($totp);

        return new CambiarTipoUsuario($repo);
    }
}
