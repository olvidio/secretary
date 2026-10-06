<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\InvitarUsuarioCentro;
use src\acceso\application\QuedaEscritorCentro;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;

final class InvitarUsuarioCentroTest extends TestCase
{
    public function testOtorgaMandatoACuentaExistentePorCorreo(): void
    {
        $identidad = new Identidad(
            5,
            'dani@x.local',
            password_hash('secret', PASSWORD_DEFAULT),
            'Dani',
            true,
            0,
            null,
            null,
            'dani',
        );
        $identidades = $this->createMock(IdentidadRepository::class);
        $identidades->method('listarPorEmail')->with('dani@x.local')->willReturn([$identidad]);
        $identidades->expects(self::once())->method('vincularCentro')->with(5, 3, 'admin');

        (new InvitarUsuarioCentro($identidades, new QuedaEscritorCentro($identidades)))
            ->ejecutar(3, 'dani@x.local', '', '', '', 'admin', true);
    }

    public function testRechazaVariasCuentasConMismoCorreo(): void
    {
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('listarPorEmail')->willReturn([
            new Identidad(1, 'x@y.local', 'h', 'A', true, 0, null, null, 'a'),
            new Identidad(2, 'x@y.local', 'h', 'B', true, 0, null, null, 'b'),
        ]);

        $this->expectException(InvalidArgumentException::class);
        (new InvitarUsuarioCentro($identidades, new QuedaEscritorCentro($identidades)))
            ->ejecutar(1, 'x@y.local');
    }

    public function testCreaCuentaNuevaSiNoHayCorreo(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $identidades->method('listarPorEmail')->willReturn([]);
        $identidades->method('porAlias')->willReturn(null);
        $identidades->expects(self::once())->method('marcarEmailVerificado');
        $identidades->expects(self::once())->method('vincularCentro')->with(88, 9, 'consulta');
        $identidades->method('guardar')->willReturnCallback(static function (Identidad $i) {
            return new Identidad(
                88,
                $i->email,
                $i->passwordHash,
                $i->nombre,
                $i->activo,
                $i->intentosFallidos,
                $i->bloqueadoHasta,
                $i->ultimoAcceso,
                $i->alias,
            );
        });
        $identidades->method('porId')->willReturn(new Identidad(
            88,
            'n@y.local',
            'h',
            'Nuevo',
            true,
            0,
            null,
            null,
            'nuevo',
        ));

        (new InvitarUsuarioCentro($identidades, new QuedaEscritorCentro($identidades)))
            ->ejecutar(9, 'n@y.local', 'nuevo', 'pass12', 'Nuevo', 'consulta', true);
    }
}
