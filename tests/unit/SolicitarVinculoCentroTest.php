<?php

declare(strict_types=1);

namespace Tests\unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\entity\VinculoCentro;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
use src\personas\application\SolicitarVinculoCentro;
use src\personas\domain\contracts\SolicitudVinculoCentroRepository;
use src\personas\domain\entity\SolicitudVinculoCentro;
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

    public function testRechazaAsociacion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('centro n');
        $centros = $this->createStub(CentroRepository::class);
        $centros->method('porId')->willReturn(new Centro(1, 'club', 'Club', 'asociacion', 'vivienda', CatalogoPlanesContables::CLUB));
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('centrosDe')->willReturn([]);
        $identidades->method('tienePersonaEnAlgunCentro')->willReturn(false);
        $solicitudes = $this->createStub(SolicitudVinculoCentroRepository::class);
        $solicitudes->method('pendienteDeIdentidad')->willReturn(null);

        (new SolicitarVinculoCentro($solicitudes, $identidades, $centros))->ejecutar(5, [
            'centro_id' => 1,
            'anio' => 2026,
        ]);
    }

    public function testSecretarioPuedePedirVinculoPersonal(): void
    {
        $centros = $this->createStub(CentroRepository::class);
        $centros->method('porId')->willReturn(new Centro(1, 'scl', 'Casa', 'n', 'vivienda', CatalogoPlanesContables::H16N));
        $identidades = $this->createStub(IdentidadRepository::class);
        $identidades->method('centrosDe')->willReturn([new VinculoCentro(1, 'admin', 'scl', 'Casa')]);
        $identidades->method('tienePersonaEnAlgunCentro')->willReturn(false);
        $solicitudes = $this->createMock(SolicitudVinculoCentroRepository::class);
        $solicitudes->method('pendienteDeIdentidad')->willReturn(null);
        $solicitudes->expects($this->once())->method('guardar')->willReturn(
            new SolicitudVinculoCentro(9, 5, 1, 2026, 'pendiente', null, null),
        );

        $out = (new SolicitarVinculoCentro($solicitudes, $identidades, $centros))->ejecutar(5, [
            'centro_id' => 1,
            'anio' => 2026,
        ]);

        self::assertSame(9, $out['id']);
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
