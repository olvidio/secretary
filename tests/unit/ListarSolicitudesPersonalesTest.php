<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\domain\contracts\IdentidadRepository;
use src\remesas\application\ListarSolicitudesPersonales;
use src\remesas\application\PersonasRemesaDeIdentidad;
use src\remesas\domain\contracts\RemesaRepository;
use src\remesas\domain\entity\SolicitudDetalle;

final class ListarSolicitudesPersonalesTest extends TestCase
{
    public function testConsultaTodasLasPersonasDeLaIdentidad(): void
    {
        $identidades = $this->createMock(IdentidadRepository::class);
        $identidades->method('personasDe')->with(9)->willReturn([3, 7]);
        $remesas = $this->createMock(RemesaRepository::class);
        $sol = new SolicitudDetalle(
            1,
            10,
            2,
            new \DateTimeImmutable('2026-03-01'),
            'pendiente',
            null,
            null,
            5,
            7,
            '22',
            2026,
            3,
            1,
        );
        $remesas->expects(self::once())
            ->method('solicitudesPendientesDePersonas')
            ->with([3, 7])
            ->willReturn([$sol]);

        $lista = (new ListarSolicitudesPersonales(
            new PersonasRemesaDeIdentidad($identidades, 9),
            $remesas,
        ))->ejecutar();

        self::assertCount(1, $lista);
        self::assertSame(7, $lista[0]['persona_id']);
        self::assertSame('22', $lista[0]['codigo_maestro']);
        self::assertNotSame('', $lista[0]['nombre'] ?? '');
    }
}
