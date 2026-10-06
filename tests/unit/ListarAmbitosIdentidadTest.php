<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\application\ListarAmbitosIdentidad;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\VinculoCentro;

final class ListarAmbitosIdentidadTest extends TestCase
{
    public function testIncluyePersonalYCadaCentro(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('centrosDe')->willReturn([
            new VinculoCentro(1, 'admin', 'n1', 'Centro N'),
            new VinculoCentro(2, 'admin', 'sg1', 'Centro SG'),
        ]);
        $repo->method('personasDe')->willReturn([10]);

        $out = (new ListarAmbitosIdentidad($repo))->ejecutar(5);
        self::assertTrue($out['puede_elegir_ambito']);
        self::assertCount(3, $out['opciones']);
        self::assertSame('persona', $out['opciones'][0]['valor']);
        self::assertSame('centro:1', $out['opciones'][1]['valor']);
        self::assertSame('centro:2', $out['opciones'][2]['valor']);
    }

    public function testTrasLoginConVariosCentrosYPersonalPideAmbito(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('centrosDe')->willReturn([
            new VinculoCentro(1, 'admin', 'n1', 'Centro N'),
            new VinculoCentro(2, 'admin', 'sg1', 'Centro SG'),
        ]);
        $repo->method('personasDe')->willReturn([10]);
        $svc = new ListarAmbitosIdentidad($repo);
        $centros = [
            ['centro_id' => 1, 'codigo' => 'n1', 'nombre' => 'Centro N', 'rol' => 'admin'],
            ['centro_id' => 2, 'codigo' => 'sg1', 'nombre' => 'Centro SG', 'rol' => 'admin'],
        ];
        self::assertSame('/elegir-ambito', $svc->rutaTrasLogin(5, 'centro', $centros, null));
    }

    public function testSoloCentrosUsaElegirCentro(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('centrosDe')->willReturn([
            new VinculoCentro(1, 'admin', 'n1', 'Centro N'),
            new VinculoCentro(2, 'admin', 'sg1', 'Centro SG'),
        ]);
        $repo->method('personasDe')->willReturn([]);
        $svc = new ListarAmbitosIdentidad($repo);
        $centros = [
            ['centro_id' => 1, 'codigo' => 'n1', 'nombre' => 'Centro N', 'rol' => 'admin'],
            ['centro_id' => 2, 'codigo' => 'sg1', 'nombre' => 'Centro SG', 'rol' => 'admin'],
        ];
        self::assertSame('/elegir-centro', $svc->rutaTrasLogin(5, 'centro', $centros, null));
    }
}
