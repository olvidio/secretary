<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
use src\asientos\domain\contracts\AsientoRepository;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;
use src\listados\application\ObtenerDonativosFundacionSg;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;
use src\plan\domain\contracts\DestinoSgRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class ObtenerDonativosFundacionSgTest extends TestCase
{
    public function testListaFundacionesYAcumuladoPorPersona(): void
    {
        $centro = new Centro(1, 'sgT', 'T', 'sg', 'vivienda', CatalogoPlanesContables::CENTRO_SG, true);
        $cfg = new ConfiguracionCentro(
            'sgT',
            2026,
            'Año',
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-06-30'),
            'vivienda',
            null,
        );
        $persona = new Persona(5, 'Ana', 'García', 'ag', null, null, null, null, null, 0, 1, true);

        $config = $this->createMock(ConfiguracionRepository::class);
        $config->method('get')->willReturn($cfg);

        $centros = $this->createMock(CentroRepository::class);
        $centros->method('porId')->willReturn($centro);

        $destinos = $this->createMock(DestinoSgRepository::class);
        $destinos->method('nombrados')->willReturn(['42' => 'Fundación X']);
        $destinos->method('paraCentro')->willReturn([
            ['codigo' => '42', 'etiqueta' => '42 Fundación X', 'orden' => 420],
        ]);

        $personas = $this->createMock(PersonaRepository::class);
        $personas->method('listarDeCentro')->willReturn([$persona]);

        $asientos = $this->createMock(AsientoRepository::class);
        $asientos->method('realizadoPorConceptoYPersona')->willReturn([
            5 => ['42' => 12500],
        ]);
        // Al 42 han salido 125 a nombre de Ana y 40 sin persona.
        $asientos->method('realizadoPorConcepto')->willReturn(['42' => 16500]);
        $asientos->method('ingresosPorPersona')->willReturn([
            5 => 30000,
            0 => 2000,
            77 => 500,
        ]);

        $ejercicios = $this->createMock(\src\ambito\domain\contracts\EjercicioRepository::class);
        $ejercicios->method('abiertoDe')->willReturn(new \src\ambito\domain\entity\Ejercicio(
            9,
            1,
            '2026',
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-12-31'),
            new DateTimeImmutable('2026-06-30'),
            'abierto',
            null,
        ));
        $resolver = new ResolverAmbitoActual($config, $centros, $ejercicios, 1);

        $uc = new ObtenerDonativosFundacionSg($resolver, $centros, $config, $destinos, $personas, $asientos);
        $sinConcepto = $uc->ejecutar(null);
        self::assertCount(3, $sinConcepto['fundaciones']);
        self::assertSame(ObtenerDonativosFundacionSg::INGRESOS_CAJA, $sinConcepto['fundaciones'][0]['codigo']);
        self::assertSame('41', $sinConcepto['fundaciones'][1]['codigo']);
        self::assertSame('2026-01-01', $sinConcepto['periodo']['desde']);
        self::assertNull($sinConcepto['destino']);
        self::assertSame([], $sinConcepto['filas']);

        $con = $uc->ejecutar('42');
        self::assertSame('42', $con['destino']['codigo']);
        self::assertSame('125,00', $con['filas'][0]['acumulado_es']);
        self::assertCount(2, $con['filas']);
        self::assertSame('Sin nombre', $con['filas'][1]['nombre']);
        self::assertSame('40,00', $con['filas'][1]['acumulado_es']);
        self::assertSame('165,00', $con['total_es']);
        self::assertSame('ag', $con['filas'][0]['iniciales']);

        // Ingresos en caja: lo ingresado menos lo que ya sale en los destinos (41+) a su nombre.
        $caja = $uc->ejecutar(ObtenerDonativosFundacionSg::INGRESOS_CAJA);
        self::assertSame('Ingresos en caja', $caja['destino']['etiqueta']);
        self::assertCount(2, $caja['filas']);
        self::assertSame('175,00', $caja['filas'][0]['acumulado_es']);
        // Sin persona y persona que ya no está en Nombres, juntas en una fila.
        self::assertSame(0, $caja['filas'][1]['persona_id']);
        self::assertSame('25,00', $caja['filas'][1]['acumulado_es']);
        self::assertSame('200,00', $caja['total_es']);
    }
}
