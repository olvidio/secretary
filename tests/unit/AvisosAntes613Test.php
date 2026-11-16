<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\informes\domain\services\AvisosAntes613;

final class AvisosAntes613Test extends TestCase
{
    public function testTodoCorrectoSinAvisos(): void
    {
        $r = AvisosAntes613::armar([
            ['id' => 'partida_doble', 'titulo' => 'Partida doble', 'ok' => true, 'mensaje' => 'ok'],
        ]);

        self::assertTrue($r['ok']);
        self::assertSame([], $r['avisos']);
    }

    public function testSoloAvisaDeLoQueFallaYDiceAdondeIr(): void
    {
        $r = AvisosAntes613::armar([
            ['id' => 'partida_doble', 'titulo' => 'Partida doble', 'ok' => true, 'mensaje' => 'ok'],
            ['id' => 'deuda_vivienda', 'titulo' => 'Deuda de vivienda', 'ok' => false, 'mensaje' => '5 persona(s)'],
            ['id' => 'meses_sin_movimiento', 'titulo' => 'Meses', 'ok' => false, 'mensaje' => 'x'],
            ['id' => 'cierre_meses_anteriores', 'titulo' => 'Cierre', 'ok' => false, 'mensaje' => 'agosto'],
        ]);

        self::assertFalse($r['ok']);
        self::assertSame(['deuda_vivienda', 'meses_sin_movimiento', 'cierre_meses_anteriores'], array_column($r['avisos'], 'id'));
        self::assertSame(['saldos', 'comprobaciones', 'cierre'], array_column($r['avisos'], 'destino'));
        self::assertStringContainsString('Saldar', $r['avisos'][0]['que_hacer']);
        self::assertSame('5 persona(s)', $r['avisos'][0]['mensaje']);
    }

    public function testDescuadreDePartidaDobleVaAApuntes(): void
    {
        $r = AvisosAntes613::armar([['id' => 'partida_doble', 'titulo' => 'Partida doble', 'ok' => false, 'mensaje' => 'x']]);

        self::assertSame('apuntes', $r['avisos'][0]['destino']);
    }
}
