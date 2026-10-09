<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\application\DesvincularUsuarioCentro;
use src\acceso\application\QuedaEscritorCentro;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;

final class DesvincularUsuarioCentroTest extends TestCase
{
    public function testRechazaQuitarseASiMismo(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $uc = new DesvincularUsuarioCentro($identidades, new QuedaEscritorCentro($identidades));

        $this->expectException(InvalidArgumentException::class);
        $uc->ejecutar(5, 1, 1);
    }

    public function testDesvinculaTrasComprobarEscritor(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $identidades->method('porId')->willReturn(new Identidad(
            2,
            's@x.local',
            'h',
            'Sec',
            false,
            0,
            null,
            null,
            'sec',
        ));
        $identidades->method('bajaCentroPendiente')->willReturn(null);
        $identidades->method('rolEnCentro')->with(2, 5)->willReturn('consulta');
        $identidades->method('usuariosDeCentro')->with(5)->willReturn([
            ['id' => 1, 'rol' => 'admin'],
            ['id' => 2, 'rol' => 'consulta'],
        ]);
        $identidades->expects(self::once())->method('desvincularCentro')->with(2, 5);

        (new DesvincularUsuarioCentro($identidades, new QuedaEscritorCentro($identidades)))->ejecutar(5, 2, 1);
    }
}
