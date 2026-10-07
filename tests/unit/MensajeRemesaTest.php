<?php

declare(strict_types=1);

namespace Tests\unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use src\remesas\domain\entity\RemesaLinea;
use src\remesas\domain\services\HashRemesa;
use src\remesas\domain\services\MensajeRemesa;

final class MensajeRemesaTest extends TestCase
{
    public function testIdaYVueltaConservaLineasDisponibleYHash(): void
    {
        $gas = new RemesaLinea(null, null, '22', 1250, [[
            'codigo' => '22.gas',
            'nombre' => 'Gas de casa',
            'cents' => 1250,
        ]]);
        $trabajo = new RemesaLinea(null, null, '111', 5000, []);
        $hash = HashRemesa::deLineas([$gas, $trabajo], 2750);
        $emitido = new DateTimeImmutable('2026-10-07T10:00:00+02:00');

        $mensaje = MensajeRemesa::componer(
            'CENTRO',
            'aa',
            2026,
            10,
            1,
            'cierre <octubre>',
            2750,
            [$gas, $trabajo],
            $hash,
            'envio',
            '6f1c2a40-7b2e-4c1a-9d0e-1a2b3c4d5e6f',
            $emitido,
        );
        $xml = $mensaje->xml();

        self::assertStringNotContainsString('22.gas', $xml);
        self::assertStringNotContainsString('Gas de casa', $xml);
        self::assertStringContainsString('cierre &lt;octubre&gt;', $xml);
        $pos111 = strpos($xml, 'codigo="111"');
        $pos22 = strpos($xml, 'codigo="22"');
        self::assertNotFalse($pos111);
        self::assertNotFalse($pos22);
        self::assertLessThan($pos111, $pos22);

        $leido = MensajeRemesa::leer($xml);
        self::assertSame($hash, $leido->hash);
        self::assertSame(2750, $leido->disponibleCents);
        self::assertSame('aa', $leido->emisorIniciales);
        self::assertSame('CENTRO', $leido->receptorCodigo);
        self::assertSame('CENTRO/aa/2026-10', $leido->conversacion());
        self::assertSame('envio', $leido->accion);
        self::assertSame(1, $leido->version);
        self::assertSame('cierre <octubre>', $leido->nota);
        self::assertSame($emitido->format(DateTimeImmutable::ATOM), $leido->emitido->format(DateTimeImmutable::ATOM));
        self::assertSame([
            ['codigo' => '22', 'cents' => 1250],
            ['codigo' => '111', 'cents' => 5000],
        ], $leido->lineas);
    }

    public function testElOrdenDeEntradaNoCambiaElDocumento(): void
    {
        $a = new RemesaLinea(null, null, '22', -300, []);
        $b = new RemesaLinea(null, null, '111', 5000, []);
        $hash = HashRemesa::deLineas([$a, $b], 0);
        $id = '6f1c2a40-7b2e-4c1a-9d0e-1a2b3c4d5e6f';
        $cuando = new DateTimeImmutable('2026-01-31T23:59:00+01:00');
        $uno = MensajeRemesa::componer('C', 'aa', 2026, 1, 2, null, 0, [$a, $b], $hash, 'sustitucion', $id, $cuando);
        $otro = MensajeRemesa::componer('C', 'aa', 2026, 1, 2, null, 0, [$b, $a], $hash, 'sustitucion', $id, $cuando);

        self::assertSame($uno->xml(), $otro->xml());
        $leido = MensajeRemesa::leer($uno->xml());
        self::assertSame('sustitucion', $leido->accion);
        self::assertSame(-300, $leido->lineas[0]['cents']);
        self::assertSame(5000, $leido->lineas[1]['cents']);
        self::assertNull($leido->nota);
    }

    public function testRechazaUnHashQueNoCoincide(): void
    {
        $linea = new RemesaLinea(null, null, '22', 1250, []);
        $hashDeOtroDisponible = HashRemesa::deLineas([$linea], 100);
        $this->expectException(InvalidArgumentException::class);
        MensajeRemesa::componer(
            'CENTRO',
            'aa',
            2026,
            10,
            1,
            null,
            200,
            [$linea],
            $hashDeOtroDisponible,
            'envio',
            '6f1c2a40-7b2e-4c1a-9d0e-1a2b3c4d5e6f',
            new DateTimeImmutable('2026-10-07T10:00:00+02:00'),
        );
    }

    public function testRechazaUnImporteDistintoAlFirmado(): void
    {
        $linea = new RemesaLinea(null, null, '22', 1250, []);
        $hash = HashRemesa::deLineas([$linea], 100);
        $xml = MensajeRemesa::componer(
            'CENTRO',
            'aa',
            2026,
            10,
            1,
            null,
            100,
            [$linea],
            $hash,
            'envio',
            '6f1c2a40-7b2e-4c1a-9d0e-1a2b3c4d5e6f',
            new DateTimeImmutable('2026-10-07T10:00:00+02:00'),
        )->xml();
        $alterado = str_replace('cents="1250"', 'cents="9999"', $xml);

        $this->expectException(InvalidArgumentException::class);
        MensajeRemesa::leer($alterado);
    }

    public function testRechazaUnaConversacionQueNoCoincide(): void
    {
        $linea = new RemesaLinea(null, null, '22', 1250, []);
        $hash = HashRemesa::deLineas([$linea], 100);
        $xml = MensajeRemesa::componer(
            'CENTRO',
            'aa',
            2026,
            10,
            1,
            null,
            100,
            [$linea],
            $hash,
            'envio',
            '6f1c2a40-7b2e-4c1a-9d0e-1a2b3c4d5e6f',
            new DateTimeImmutable('2026-10-07T10:00:00+02:00'),
        )->xml();
        $alterado = str_replace('CENTRO/aa/2026-10', 'OTRO/aa/2026-10', $xml);

        $this->expectException(InvalidArgumentException::class);
        MensajeRemesa::leer($alterado);
    }
}
