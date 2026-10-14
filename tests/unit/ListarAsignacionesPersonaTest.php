<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\domain\contracts\IdentidadRepository;
use src\disponible\application\ListarAsignacionesPersona;
use src\disponible\domain\contracts\AsignacionLaboresRepository;
use src\mensajes\application\SincronizarBandeja;
use src\mensajes\domain\contracts\MensajeRepository;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\remesas\domain\contracts\RemesaRepository;

final class ListarAsignacionesPersonaTest extends TestCase
{
    public function testLeeLasLaboresDeLaPersonaDelCentroNoDelLibroPersonal(): void
    {
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('personasVinculoDe')->willReturn([
            [
                'persona_id' => 20,
                'centro_id' => 3,
                'iniciales' => 'ab',
            ],
        ]);
        $asignaciones = $this->createStub(AsignacionLaboresRepository::class);
        $asignaciones->method('instruccionesPendientesDePersona')
            ->with(3, 20)
            ->willReturn([
                ['codigo_maestro' => '71', 'importe_cents' => 15000, 'importe_es' => '150,00'],
            ]);
        $partidas = $this->createStub(PartidaLaboresRepository::class);
        $partidas->method('paraCentro')->willReturn([
            ['codigo' => '71', 'etiqueta' => 'Prelatura', 'orden' => 1, 'desgrava' => false],
        ]);
        $sincronizar = new SincronizarBandeja(
            $identidades,
            $asignaciones,
            $partidas,
            $this->createStub(RemesaRepository::class),
            $this->createStub(MensajeRepository::class),
        );

        $out = (new ListarAsignacionesPersona($identidades, $asignaciones, $partidas, $sincronizar, 7))->ejecutar();

        self::assertStringContainsString('71 Prelatura', $out['texto']);
        self::assertStringContainsString('Deberías ingresar', $out['texto']);
    }
}
