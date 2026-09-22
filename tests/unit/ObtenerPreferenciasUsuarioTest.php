<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\acceso\application\ObtenerPreferenciasUsuario;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\Identidad;
use src\acceso\domain\entity\VinculoCentro;

final class ObtenerPreferenciasUsuarioTest extends TestCase
{
    public function testPuedeCambiarTipoSoloConCentroYPersona(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('porId')->willReturn(new Identidad(
            1,
            'a@x.local',
            'h',
            'Ana',
            true,
            0,
            null,
            null,
            'ana',
        ));
        $repo->method('layoutDe')->willReturn('excel');
        $repo->method('idiomaDe')->willReturn('es');
        $repo->method('centrosDe')->willReturn([new VinculoCentro(3, 'admin', 'c', 'Centro')]);
        $repo->method('personasDe')->willReturn([9]);
        $repo->method('personasVinculoDe')->willReturn([
            ['persona_id' => 1, 'iniciales' => 'a', 'nombre_completo' => 'A', 'centro_id' => 3],
            ['persona_id' => 2, 'iniciales' => 'b', 'nombre_completo' => 'B', 'centro_id' => 4],
        ]);
        $repo->method('totpConfirmado')->willReturn(true);

        $out = (new ObtenerPreferenciasUsuario($repo))->ejecutar(1);
        self::assertTrue($out['puede_cambiar_tipo']);
        self::assertTrue($out['puede_elegir_persona_activa']);
    }

    public function testSoloPersonalNoPuedeCambiarTipo(): void
    {
        $repo = $this->createStub(IdentidadRepository::class);
        $repo->method('porId')->willReturn(new Identidad(
            2,
            'b@x.local',
            'h',
            'Bea',
            true,
            0,
            null,
            null,
            'bea',
        ));
        $repo->method('layoutDe')->willReturn('excel');
        $repo->method('idiomaDe')->willReturn('es');
        $repo->method('centrosDe')->willReturn([]);
        $repo->method('personasDe')->willReturn([5]);
        $repo->method('personasVinculoDe')->willReturn([]);
        $repo->method('totpConfirmado')->willReturn(false);

        $out = (new ObtenerPreferenciasUsuario($repo))->ejecutar(2);
        self::assertFalse($out['puede_cambiar_tipo']);
        self::assertFalse($out['puede_centro']);
        self::assertTrue($out['puede_persona']);
        self::assertFalse($out['puede_elegir_persona_activa']);
    }
}
