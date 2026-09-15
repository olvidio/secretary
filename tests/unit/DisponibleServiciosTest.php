<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use src\disponible\domain\services\ConstructorAsientoAparcamiento;
use src\disponible\domain\services\SobranteRemesa;
use src\disponible\domain\services\TramosDesgravacion;

final class DisponibleServiciosTest extends TestCase
{
    public function testSobranteEsIngresosMenosGastos(): void
    {
        self::assertSame(3750, SobranteRemesa::cents([
            ['codigo_maestro' => '111', 'importe_cents' => 5000],
            ['codigo_maestro' => '22', 'importe_cents' => 1250],
        ]));
    }

    public function testAparcamientoPositivoDejaLaCcACero(): void
    {
        $a = ConstructorAsientoAparcamiento::construir(
            1,
            9,
            new DateTimeImmutable('2026-01-31'),
            44,
            90,
            91,
            3750,
            'Aparcar sobrante AA 01/2026 v1',
        );
        self::assertNotNull($a);
        self::assertSame('remesa', $a->origen);
        $a->assertCuadre();
        $por = [];
        foreach ($a->movimientos as $m) {
            $por[$m->cuentaId] = $m;
        }
        self::assertSame(3750, $por[91]->debeCents);
        self::assertSame(3750, $por[90]->haberCents);
    }

    public function testCapacidadDelPrimerTramo(): void
    {
        $tramo = TramosDesgravacion::porDefecto()[0];
        self::assertSame(25000, TramosDesgravacion::capacidadTramo($tramo, 0, 0));
        self::assertSame(15000, TramosDesgravacion::capacidadTramo($tramo, 10000, 0));
        self::assertSame(0, TramosDesgravacion::capacidadTramo($tramo, 25000, 0));
    }
}
