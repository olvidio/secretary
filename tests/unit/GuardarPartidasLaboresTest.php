<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Centro;
use src\ambito\domain\entity\Ejercicio;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\plan\application\GuardarPartidasLabores;
use src\plan\domain\contracts\PartidaLaboresRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class GuardarPartidasLaboresTest extends TestCase
{
    public function testNormalizaOrden(): void
    {
        $guardadas = null;
        $partidas = $this->createMock(PartidaLaboresRepository::class);
        $partidas->expects(self::once())
            ->method('guardar')
            ->willReturnCallback(static function (int $id, array $rows) use (&$guardadas): void {
                $guardadas = $rows;
            });
        $partidas->method('paraCentro')->willReturn([]);

        $centros = $this->createMock(CentroRepository::class);
        $centros->method('porId')->willReturn(
            new Centro(2, 'CTR2', 'Centro 2', 'vivienda', CatalogoPlanesContables::H16N)
        );

        $resolver = new ResolverAmbitoActual(
            $this->createMock(ConfiguracionRepository::class),
            $centros,
            $this->ejerciciosMock(),
            2,
        );

        $caso = new GuardarPartidasLabores($resolver, $partidas);
        $caso->ejecutar([
            'partidas' => [
                ['codigo' => '71', 'etiqueta' => 'Uno'],
                ['codigo' => '72', 'etiqueta' => 'Dos'],
            ],
        ]);

        self::assertSame([
            ['codigo' => '71', 'etiqueta' => 'Uno', 'orden' => 10],
            ['codigo' => '72', 'etiqueta' => 'Dos', 'orden' => 20],
        ], $guardadas);
    }

    public function testRechazaListaVacia(): void
    {
        $partidas = $this->createMock(PartidaLaboresRepository::class);
        $partidas->expects(self::never())->method('guardar');

        $centros = $this->createMock(CentroRepository::class);
        $centros->method('porId')->willReturn(
            new Centro(1, 'CTR', 'Centro', 'vivienda', CatalogoPlanesContables::H16N)
        );

        $resolver = new ResolverAmbitoActual(
            $this->createMock(ConfiguracionRepository::class),
            $centros,
            $this->ejerciciosMock(),
            1,
        );

        $caso = new GuardarPartidasLabores($resolver, $partidas);

        $this->expectException(InvalidArgumentException::class);
        $caso->ejecutar(['partidas' => []]);
    }

    private function ejerciciosMock(): EjercicioRepository
    {
        $ejercicios = $this->createMock(EjercicioRepository::class);
        $ejercicios->method('abiertoDe')->willReturn(
            new Ejercicio(
                1,
                1,
                '2026',
                new DateTimeImmutable('2026-01-01'),
                new DateTimeImmutable('2026-12-31'),
                new DateTimeImmutable('2026-06-30'),
            )
        );

        return $ejercicios;
    }
}
