<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\ambito\application\ResolverAmbitoActual;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Centro;
use src\ambito\domain\entity\Ejercicio;
use src\configuracion\application\GuardarConfiguracion;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;
use src\plan\domain\contracts\PlanContableRepository;
use src\plan\domain\services\CatalogoPlanesContables;

final class GuardarConfiguracionTest extends TestCase
{
    public function testSoloFechaCierreEnCentroSgNoExigeSigla(): void
    {
        $ini = new DateTimeImmutable('2026-01-01');
        $corte = new DateTimeImmutable('2026-06-30');
        $actual = new ConfiguracionCentro('legacy', 2026, 'Año', $ini, $corte, 'vivienda', null, null);
        $centro = new Centro(1, 'sgMontagut', 'Montagut', 'sg', 'vivienda', CatalogoPlanesContables::CENTRO_SG, true);
        $ejercicio = new Ejercicio(10, 1, '2026', $ini, new DateTimeImmutable('2026-12-31'), $corte, 'abierto', null);

        $repo = $this->createMock(ConfiguracionRepository::class);
        $repo->method('get')->willReturn($actual);
        $repo->expects(self::once())->method('guardar')->with(self::callback(
            static fn (ConfiguracionCentro $c): bool => $c->fechaCierre->format('Y-m-d') === '2026-07-31',
        ));

        $centros = $this->createMock(CentroRepository::class);
        $centros->method('porId')->willReturn($centro);

        $planes = $this->createMock(PlanContableRepository::class);
        $planes->method('idPorCodigo')->with(CatalogoPlanesContables::CENTRO_SG)->willReturn(1);

        $ejercicios = $this->createMock(EjercicioRepository::class);
        $ejercicios->method('abiertoDe')->with(1)->willReturn($ejercicio);
        $ejercicios->method('porId')->with(10)->willReturn($ejercicio);
        $ejercicios->expects(self::once())->method('guardar')->willReturn($ejercicio);

        $ambito = new ResolverAmbitoActual($repo, $centros, $ejercicios, 1);

        $uc = new GuardarConfiguracion($repo, $ambito, $centros, $planes, $ejercicios);
        $uc->ejecutar(['fecha_cierre' => '2026-07-31']);
    }
}
