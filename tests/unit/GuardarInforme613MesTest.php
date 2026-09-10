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
use src\informes\application\GuardarInforme613Mes;
use src\informes\domain\contracts\Informe613MesRepository;
use src\informes\domain\entity\Informe613Mes;
use src\plan\domain\services\CatalogoPlanesContables;

final class GuardarInforme613MesTest extends TestCase
{
    public function testGuardaCamposManualesDelMes(): void
    {
        $guardado = null;
        $repo = $this->createMock(Informe613MesRepository::class);
        $repo->method('buscar')->willReturn(null);
        $repo->expects(self::once())
            ->method('guardar')
            ->willReturnCallback(static function (Informe613Mes $informe) use (&$guardado): void {
                $guardado = $informe;
            });

        $caso = new GuardarInforme613Mes($this->resolverAmbito(), $repo);
        $caso->ejecutar('P', [
            'fecha_cierre' => '2026-06-30',
            'observaciones' => 'Nota junio',
            'saldo_cc_personales' => '1.234,56',
        ]);

        self::assertSame('Nota junio', $guardado->observaciones);
        self::assertSame('1.234,56', $guardado->saldoCcPersonales);
        self::assertSame('2026-06-30', $guardado->fechaCierre->format('Y-m-d'));
    }

    public function testRechazaFechaCierreFaltante(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $caso = new GuardarInforme613Mes(
            $this->resolverAmbito(),
            $this->createMock(Informe613MesRepository::class),
        );
        $caso->ejecutar('G', ['observaciones' => 'x']);
    }

    private function resolverAmbito(): ResolverAmbitoActual
    {
        $centros = $this->createMock(CentroRepository::class);
        $centros->method('porId')->willReturn(
            new Centro(2, 'CTR2', 'Centro 2', 'vivienda', CatalogoPlanesContables::H16N)
        );

        return new ResolverAmbitoActual(
            $this->createMock(ConfiguracionRepository::class),
            $centros,
            $this->ejerciciosMock(),
            2,
        );
    }

    private function ejerciciosMock(): EjercicioRepository
    {
        $ejercicios = $this->createMock(EjercicioRepository::class);
        $ejercicios->method('abiertoDe')->willReturn(new Ejercicio(
            9,
            2,
            '2026',
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-12-31'),
            new DateTimeImmutable('2026-06-30'),
        ));

        return $ejercicios;
    }
}
