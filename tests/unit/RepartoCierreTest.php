<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\cierre\domain\services\RepartoCierre;
use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

final class RepartoCierreTest extends TestCase
{
    public function testExentosYCuota(): void
    {
        $a = new Persona(1, 'Ana', 'A', 'aa', null, null, null, null, null, 1);
        $b = new Persona(2, 'Bea', 'B', 'bb', 1, 12, null, null, null, 2);
        $c = new Persona(3, 'Cal', 'C', 'cc', null, null, null, null, new Dinero('100.00'), 3);
        $d = new Persona(4, 'Dani', 'D', 'dd', null, null, null, null, null, 4, null, true, null, false);
        $r = RepartoCierre::calcular(new Dinero('200.00'), [$a, $b, $c, $d], 6);
        self::assertCount(2, $r);
        self::assertSame('aa', $r[0]['persona']->iniciales);
        self::assertSame('100.00', $r[0]['importe']->toString());
        self::assertSame('cc', $r[1]['persona']->iniciales);
        self::assertSame('100.00', $r[1]['importe']->toString());
    }

    public function testDescuentaAportacionesDeQuienNoEntraEnReparto(): void
    {
        $a = new Persona(1, 'Ana', 'A', 'aa', null, null, null, null, null, 1);
        $b = new Persona(2, 'Bea', 'B', 'bb', null, null, null, null, null, 2);
        $c = new Persona(3, 'Cal', 'C', 'cc', null, null, null, null, null, 3, null, true, null, false);
        $aportaciones = ['cc' => new Dinero('30.00'), 'aa' => new Dinero('50.00')];
        self::assertSame('270.00', RepartoCierre::gastosNetosParaReparto(
            new Dinero('300.00'),
            [$a, $b, $c],
            4,
            $aportaciones,
        )->toString());

        $r = RepartoCierre::calcular(new Dinero('300.00'), [$a, $b, $c], 4, $aportaciones);
        self::assertSame('135.00', $r[0]['importe_bruto']->toString());
        self::assertSame('85.00', $r[0]['importe']->toString());
        self::assertSame('135.00', $r[1]['importe']->toString());
    }

    public function testRestaLoYaImputado(): void
    {
        $a = new Persona(1, 'Ana', 'A', 'aa', null, null, null, null, null, 1);
        $b = new Persona(2, 'Bea', 'B', 'bb', null, null, null, null, null, 2);
        $r = RepartoCierre::calcular(
            new Dinero('200.00'),
            [$a, $b],
            3,
            ['aa' => new Dinero('50.00')],
        );
        self::assertSame('100.00', $r[0]['importe_bruto']->toString());
        self::assertSame('50.00', $r[0]['ya_imputado']->toString());
        self::assertSame('50.00', $r[0]['importe']->toString());
        self::assertSame('100.00', $r[1]['importe']->toString());
    }

    public function testElExcesoDeUnoReduceLoQueFaltaPorCubrir(): void
    {
        $a = new Persona(1, 'Ana', 'A', 'aa', null, null, null, null, null, 1);
        $b = new Persona(2, 'Bea', 'B', 'bb', null, null, null, null, null, 2);
        $r = RepartoCierre::calcular(
            new Dinero('300.00'),
            [$a, $b],
            5,
            ['aa' => new Dinero('200.00')],
        );
        self::assertSame('0.00', $r[0]['importe']->toString());
        self::assertSame('100.00', $r[1]['importe']->toString());
        self::assertSame('100.00', RepartoCierre::restantePorCubrir(
            new Dinero('300.00'),
            ['aa' => new Dinero('200.00')],
        )->toString());
    }

    public function testNoGeneraCierreSiYaEstaTodoPagado(): void
    {
        $a = new Persona(1, 'Ana', 'A', 'aa', null, null, null, null, null, 1);
        $r = RepartoCierre::calcular(
            new Dinero('100.00'),
            [$a],
            5,
            ['aa' => new Dinero('120.00')],
        );
        self::assertSame('0.00', $r[0]['importe']->toString());
    }

    public function testAdelantoDelEjercicioNoCargaEsteMes(): void
    {
        $a = new Persona(1, 'Ana', 'A', 'aa', null, null, null, null, null, 1);
        $b = new Persona(2, 'Bea', 'B', 'bb', null, null, null, null, null, 2);
        $r = RepartoCierre::calcular(
            new Dinero('200.00'),
            [$a, $b],
            6,
            [],
            ['aa' => new Dinero('250.00')],
            ['aa' => new Dinero('200.00'), 'bb' => new Dinero('200.00')],
        );
        self::assertSame('0.00', $r[0]['importe']->toString());
        self::assertSame('250.00', $r[0]['ya_imputado']->toString());
        self::assertSame('200.00', $r[1]['importe']->toString());
    }

    public function testSiTodosVanAdelantadosElPendienteDelMesSeReparte(): void
    {
        $a = new Persona(1, 'Ana', 'A', 'aa', null, null, null, null, null, 1);
        $b = new Persona(2, 'Bea', 'B', 'bb', null, null, null, null, null, 2);
        $r = RepartoCierre::calcular(
            new Dinero('200.00'),
            [$a, $b],
            6,
            [],
            ['aa' => new Dinero('300.00'), 'bb' => new Dinero('300.00')],
            ['aa' => new Dinero('200.00'), 'bb' => new Dinero('200.00')],
        );
        self::assertSame('100.00', $r[0]['importe']->toString());
        self::assertSame('100.00', $r[1]['importe']->toString());
    }

    public function testObjetivoAcumuladoSumaCuotasDeCadaMes(): void
    {
        $a = new Persona(1, 'Ana', 'A', 'aa', null, null, null, null, null, 1);
        $b = new Persona(2, 'Bea', 'B', 'bb', null, null, null, null, null, 2);
        $out = RepartoCierre::objetivoAcumulado([$a, $b], [
            ['mes' => 1, 'gastos' => new Dinero('200.00'), 'aportaciones' => []],
            ['mes' => 2, 'gastos' => new Dinero('100.00'), 'aportaciones' => []],
        ]);
        self::assertSame('150.00', $out['aa']->toString());
        self::assertSame('150.00', $out['bb']->toString());
    }
}
