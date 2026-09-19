<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\envio_dl\domain\services\RepartidorEnvioDl;
use src\personas\domain\entity\Persona;
use src\shared\domain\value_objects\Dinero;

final class RepartidorEnvioDlTest extends TestCase
{
    public function testEntranTodosLosActivosAunqueNoAportenVivienda(): void
    {
        $personas = [
            $this->persona(1, 'aaa', false),
            $this->persona(2, 'bbb', true),
        ];
        $saldos = [1 => 5000, 2 => 5000];
        $lineas = RepartidorEnvioDl::repartir(Dinero::fromInput('10'), $personas, $saldos);
        self::assertCount(2, $lineas);
    }

    public function testNoExcluyeExentosDelMes(): void
    {
        $personas = [
            $this->persona(1, 'aaa', true, 1, 12),
            $this->persona(2, 'bbb', true),
        ];
        $saldos = [1 => 10000, 2 => 10000];
        $lineas = RepartidorEnvioDl::repartir(Dinero::fromInput('10'), $personas, $saldos);
        self::assertCount(2, $lineas);
    }

    public function testExcluyeSaldoNegativo(): void
    {
        $personas = [
            $this->persona(1, 'aaa', true),
            $this->persona(2, 'bbb', true),
            $this->persona(3, 'ccc', true),
        ];
        $saldos = [1 => 10000, 2 => -20000, 3 => 10000];
        $lineas = RepartidorEnvioDl::repartir(Dinero::fromInput('100'), $personas, $saldos);
        self::assertSame([
            1 => 5000,
            3 => 5000,
        ], $this->mapLineas($lineas));
    }

    public function testExcluyeSaldoCero(): void
    {
        $personas = [
            $this->persona(1, 'aaa', true),
            $this->persona(2, 'bbb', true),
        ];
        $saldos = [1 => 0, 2 => 0];
        $lineas = RepartidorEnvioDl::repartir(Dinero::fromInput('10'), $personas, $saldos);
        self::assertSame([], $lineas);
    }

    public function testConAlgunoACeroSoloReparteEntreSaldoPositivo(): void
    {
        $personas = [
            $this->persona(1, 'aaa', true),
            $this->persona(2, 'bbb', true),
            $this->persona(3, 'ccc', true),
        ];
        $saldos = [1 => 10000, 2 => 0, 3 => 10000];
        $lineas = RepartidorEnvioDl::repartir(Dinero::fromInput('90'), $personas, $saldos);
        self::assertSame([1 => 4500, 3 => 4500], $this->mapLineas($lineas));
    }

    public function testRedondeaAEurosEnteros(): void
    {
        $personas = [
            $this->persona(1, 'aaa', true),
            $this->persona(2, 'bbb', true),
            $this->persona(3, 'ccc', true),
        ];
        $saldos = [1 => 10000, 2 => 10000, 3 => 10000];
        $lineas = RepartidorEnvioDl::repartir(Dinero::fromInput('10'), $personas, $saldos);
        foreach ($lineas as $l) {
            self::assertSame(0, $l['importe_cents'] % 100);
        }
        self::assertSame(1000, array_sum(array_column($lineas, 'importe_cents')));
    }

    public function testSinSaldoPositivoNoReparte(): void
    {
        $personas = [
            $this->persona(1, 'aaa', true),
            $this->persona(2, 'bbb', true),
        ];
        $lineas = RepartidorEnvioDl::repartir(Dinero::fromInput('10'), $personas, []);
        self::assertSame([], $lineas);
    }

    /** @param list<array{persona_id:int, importe_cents:int}> $lineas */
    private function mapLineas(array $lineas): array
    {
        $out = [];
        foreach ($lineas as $l) {
            $out[$l['persona_id']] = $l['importe_cents'];
        }

        return $out;
    }

    private function persona(
        int $id,
        string $iniciales,
        bool $aporta,
        ?int $exIni = null,
        ?int $exFin = null,
    ): Persona {
        return new Persona(
            $id,
            'Nombre',
            strtoupper($iniciales),
            $iniciales,
            $exIni,
            $exFin,
            null,
            null,
            null,
            $id,
            null,
            true,
            null,
            $aporta,
        );
    }
}
