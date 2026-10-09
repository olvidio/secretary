<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\mensajes\application\PresentadorMensaje;

final class PresentadorMensajeTest extends TestCase
{
    public function testDestinosLlevaElTextoYElEnlaceALaRemesa(): void
    {
        $fila = PresentadorMensaje::presentar([
            'id' => 4,
            'tipo' => 'destinos_7',
            'payload' => (string) json_encode([
                'lineas' => [
                    ['codigo' => '71', 'etiqueta' => 'Prelatura', 'cents' => 10000],
                ],
            ]),
            'leido_at' => null,
            'creado_at' => '2026-10-09 18:00:00+02',
        ]);

        self::assertSame('Destinos de labores', $fila['titulo']);
        self::assertStringContainsString('71 Prelatura', $fila['cuerpo']);
        self::assertSame('/yo/remesas', $fila['href']);
        self::assertSame('Ver en la remesa', $fila['accion']);
        self::assertFalse($fila['leido']);
        self::assertSame('2026-10-09', $fila['fecha']);
    }

    public function testDetallePideIrALaRemesa(): void
    {
        $fila = PresentadorMensaje::presentar([
            'id' => 5,
            'tipo' => 'remesa_detalle',
            'payload' => (string) json_encode([
                'codigo' => '22',
                'nombre' => 'Ordinarios',
                'mes' => 3,
                'anio' => 2026,
                'version' => 2,
            ]),
            'leido_at' => '2026-10-09 19:00:00+02',
            'creado_at' => '2026-10-09 18:00:00+02',
        ]);

        self::assertSame('El centro pide el detalle de una remesa', $fila['titulo']);
        self::assertStringContainsString('22 Ordinarios', $fila['cuerpo']);
        self::assertStringContainsString('03/2026', $fila['cuerpo']);
        self::assertSame('Ir a la remesa', $fila['accion']);
        self::assertTrue($fila['leido']);
    }
}
