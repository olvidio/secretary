<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use src\presupuestos\application\ObtenerPrevisionPersonal;

/** La columna acumulado(anterior) debe usar acumulado real + previsión del ejercicio abierto, no la proyección. */
final class ReferenciaPrevisionPersonalTest extends TestCase
{
    public function testReferenciaUsaAcumuladoYNoProyeccion(): void
    {
        $filas = $this->filasAgrupadas([
            [
                'codigo' => '111',
                'etiqueta' => 'Sueldo',
                'grupo' => 'I',
                'modo' => 'lineal',
                'acumulado_cents' => 60000,
                'acumulado_es' => '600,00',
                'calculado_cents' => 120000,
                'calculado_es' => '1.200,00',
                'previsto_cents' => null,
                'previsto_ejercicio_actual_cents' => 55000,
                'previsto_ejercicio_actual_es' => '550,00',
            ],
        ]);
        $f = self::filaPorCodigo($filas, '111');
        self::assertNotNull($f);
        self::assertSame('600 (550)', $f['referencia_es']);
        self::assertSame('1.200,00', $f['calculado_es']);
        self::assertNotSame($f['referencia_es'], $f['calculado_es']);
    }

    public function testReferenciaSoloAcumuladoSinPrevisionGuardada(): void
    {
        $filas = $this->filasAgrupadas([
            [
                'codigo' => '22',
                'etiqueta' => 'Ordinarios',
                'grupo' => 'II',
                'modo' => 'lineal',
                'acumulado_cents' => 12345,
                'acumulado_es' => '123,45',
                'calculado_cents' => 99999,
                'calculado_es' => '999,99',
                'previsto_cents' => null,
                'previsto_ejercicio_actual_cents' => null,
            ],
        ]);
        $f = self::filaPorCodigo($filas, '22');
        self::assertNotNull($f);
        self::assertSame('123', $f['referencia_es']);
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return array<string, mixed>|null
     */
    private static function filaPorCodigo(array $filas, string $codigo): ?array
    {
        foreach ($filas as $f) {
            if (($f['codigo'] ?? '') === $codigo) {
                return $f;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $lineas
     * @return list<array<string, mixed>>
     */
    private function filasAgrupadas(array $lineas): array
    {
        $m = new ReflectionMethod(ObtenerPrevisionPersonal::class, 'filasAgrupadas');
        $m->setAccessible(true);

        return $m->invoke(null, $lineas);
    }
}
