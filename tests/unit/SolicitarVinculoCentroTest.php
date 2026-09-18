<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
use src\personas\application\SolicitarVinculoCentro;
use src\personas\domain\contracts\SolicitudVinculoCentroRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class SolicitarVinculoCentroTest extends TestCase
{
    public function testRechazaCentroSg(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $centros = $this->createStub(CentroRepository::class);
        $centros->method('porId')->willReturn(new Centro(1, 'sg1', 'SG', 'sg', 'necesidades', CatalogoPlanesContables::H16N));
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('centrosDe')->willReturn([]);
        $identidades->method('personasDe')->willReturn([]);
        $solicitudes = $this->createStub(SolicitudVinculoCentroRepository::class);
        $solicitudes->method('pendienteDeIdentidad')->willReturn(null);

        (new SolicitarVinculoCentro($solicitudes, $identidades, $centros))->ejecutar(5, [
            'centro_id' => 1,
            'anio' => 2026,
        ]);
    }

    public function testRechazaSiYaHayVinculo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('centrosDe')->willReturn([]);
        $identidades->method('personasDe')->willReturn([10]);
        (new SolicitarVinculoCentro(
            $this->createStub(SolicitudVinculoCentroRepository::class),
            $identidades,
            $this->createStub(CentroRepository::class),
        ))->ejecutar(5, ['centro_id' => 1, 'anio' => 2026]);
    }
}
