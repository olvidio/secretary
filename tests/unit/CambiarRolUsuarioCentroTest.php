<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\CambiarRolUsuarioCentro;
use src\acceso\application\QuedaEscritorCentro;
use src\acceso\domain\contracts\IdentidadRepository;

final class CambiarRolUsuarioCentroTest extends TestCase
{
    public function testPasaAConsultaSiQuedaOtroQueModifica(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('rolEnCentro')->willReturn('admin');
        $repo->method('usuariosDeCentro')->willReturn([
            $this->usuario(1, 'admin'),
            $this->usuario(2, 'admin'),
        ]);
        $repo->expects($this->once())->method('vincularCentro')->with(1, 10, 'consulta');

        (new CambiarRolUsuarioCentro($repo, new QuedaEscritorCentro($repo)))
            ->ejecutar(10, 1, 'consulta');
    }

    public function testNoDejaElCentroSinQuienModifique(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('rolEnCentro')->willReturn('admin');
        $repo->method('usuariosDeCentro')->willReturn([
            $this->usuario(1, 'admin'),
            $this->usuario(2, 'consulta'),
        ]);
        $repo->expects($this->never())->method('vincularCentro');
        $caso = new CambiarRolUsuarioCentro($repo, new QuedaEscritorCentro($repo));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pueda modificar');
        $caso->ejecutar(10, 1, 'consulta');
    }

    public function testIgnoraSiElRolNoCambia(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('rolEnCentro')->willReturn('consulta');
        $repo->expects($this->never())->method('vincularCentro');

        (new CambiarRolUsuarioCentro($repo, new QuedaEscritorCentro($repo)))
            ->ejecutar(10, 1, 'consulta');
    }

    public function testRechazaQuienNoEstaEnElCentro(): void
    {
        $repo = $this->createMock(IdentidadRepository::class);
        $repo->method('rolEnCentro')->willReturn(null);
        $repo->expects($this->never())->method('vincularCentro');
        $caso = new CambiarRolUsuarioCentro($repo, new QuedaEscritorCentro($repo));

        $this->expectException(InvalidArgumentException::class);
        $caso->ejecutar(10, 9, 'consulta');
    }

    /** @return array{id:int, email:string, alias:?string, nombre:string, rol:string} */
    private function usuario(int $id, string $rol): array
    {
        return [
            'id' => $id,
            'email' => 'u' . $id . '@test',
            'alias' => 'u' . $id,
            'nombre' => 'U' . $id,
            'rol' => $rol,
        ];
    }
}
