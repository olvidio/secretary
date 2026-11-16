<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\EjercicioRepository;
use src\ambito\domain\entity\Centro;
use src\ambito\domain\entity\Ejercicio;
use src\configuracion\domain\contracts\ConfiguracionRepository;
use src\configuracion\domain\entity\ConfiguracionCentro;
use src\configuracion\infrastructure\persistence\ConfiguracionDelEjercicio;

/** Fecha de cierre, tipo de cierre y sigla son de cada centro, no de la fila global. */
final class ConfiguracionDelEjercicioTest extends TestCase
{
    private function repo(?int $centroSesion): ConfiguracionDelEjercicio
    {
        // Fila global con la fecha de otro centro (el fallo: 01/01 de un libro personal).
        $global = new ConfiguracionCentro(
            '', 2026, 'Año', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-01-01'), 'vivienda', '7',
        );
        $base = $this->createStub(ConfiguracionRepository::class);
        $base->method('get')->willReturn($global);

        $montagut = new Centro(2, 'Montagut', 'Montagut', 'n', 'vivienda', 'H16n');
        $daniel = new Centro(8, 'p-dserrabou', 'Daniel', 'n', 'necesidades', 'H16n');
        $sinEjercicio = new Centro(9, 'nuevo', 'Nuevo', 'n', 'necesidades', 'H16n');
        $centros = $this->createStub(CentroRepository::class);
        $centros->method('porId')->willReturnMap([[2, $montagut], [8, $daniel], [9, $sinEjercicio]]);
        $centros->method('listar')->willReturn([$montagut, $daniel, $sinEjercicio]);

        $ejercicios = $this->createStub(EjercicioRepository::class);
        $ejercicios->method('abiertoDe')->willReturnMap([
            [2, new Ejercicio(2, 2, '2026', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'), new DateTimeImmutable('2026-10-31'), 'abierto')],
            [8, new Ejercicio(17, 8, '2026', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'), new DateTimeImmutable('2026-01-01'), 'abierto')],
        ]);

        return new ConfiguracionDelEjercicio($base, $centros, $ejercicios, $centroSesion);
    }

    public function testCadaCentroVeLaFechaDeCierreDeSuEjercicio(): void
    {
        self::assertSame('2026-10-31', $this->repo(2)->get()->fechaCierre->format('Y-m-d'));
        self::assertSame('2026-01-01', $this->repo(8)->get()->fechaCierre->format('Y-m-d'));
    }

    public function testElTipoDeCierreEsElDelCentroNoElDeLaFilaGlobal(): void
    {
        self::assertSame('vivienda', $this->repo(2)->get()->tipoCierre);
        self::assertSame('necesidades', $this->repo(8)->get()->tipoCierre);
    }

    public function testCentroSinEjercicioAbiertoTambienUsaSuTipoDeCierre(): void
    {
        $cfg = $this->repo(9)->get();

        self::assertSame('necesidades', $cfg->tipoCierre);
        self::assertSame('2026-01-01', $cfg->fechaCierre->format('Y-m-d'));
    }

    public function testLaSiglaEsLaDelCentro(): void
    {
        self::assertSame('Montagut', $this->repo(2)->get()->centro);
        self::assertSame('p-dserrabou', $this->repo(8)->get()->centro);
    }

    public function testSinSesionUsaElPrimerCentro(): void
    {
        $cfg = $this->repo(null)->get();

        self::assertSame('2026-10-31', $cfg->fechaCierre->format('Y-m-d'));
        self::assertSame('Montagut', $cfg->centro);
        self::assertSame('vivienda', $cfg->tipoCierre);
        self::assertSame(10, $cfg->periodo()->mesesTranscurridos());
    }
}
