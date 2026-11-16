<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\cierre\domain\services\ComparadorCierreMes;

final class ComparadorCierreMesTest extends TestCase
{
    public function testSinNadaQueRepartirNoAplica(): void
    {
        self::assertSame('no_aplica', ComparadorCierreMes::comparar([], [])['estado']);
        self::assertSame('no_aplica', ComparadorCierreMes::comparar(['ac' => 0], [])['estado']);
    }

    public function testIgualQueLoGeneradoEstaAlDia(): void
    {
        $r = ComparadorCierreMes::comparar(['ac' => 10000, 'fb' => 5000], ['fb' => 5000, 'ac' => 10000]);

        self::assertSame('ok', $r['estado']);
        self::assertSame([], $r['diferencias']);
    }

    public function testNadaGeneradoYHayRepartoEstaSinGenerar(): void
    {
        $r = ComparadorCierreMes::comparar(['ac' => 10000], []);

        self::assertSame('sin_generar', $r['estado']);
        self::assertSame([['iniciales' => 'ac', 'generado_es' => '0,00', 'deberia_es' => '100,00']], $r['diferencias']);
    }

    public function testImportesDistintosEstaDesactualizado(): void
    {
        $r = ComparadorCierreMes::comparar(['ac' => 12000], ['ac' => 10000, 'fb' => 3000]);

        self::assertSame('desactualizado', $r['estado']);
        self::assertCount(2, $r['diferencias']);
        self::assertSame('fb', $r['diferencias'][1]['iniciales']);
        self::assertSame('0,00', $r['diferencias'][1]['deberia_es']);
    }

    /** Regresión: un mes con su cierre ya anotado no debe salir como «falta el cierre». */
    public function testMesYaCerradoNuncaSaleSinGenerarAunqueNoCoincida(): void
    {
        $r = ComparadorCierreMes::comparar(['ac' => 102973, 'rd' => 100029], ['ac' => 82936, 'rd' => 80140]);

        self::assertNotSame('sin_generar', $r['estado']);
    }

    public function testGeneradoQueYaNoHaceFaltaEstaDesactualizado(): void
    {
        self::assertSame('desactualizado', ComparadorCierreMes::comparar([], ['ac' => 10000])['estado']);
    }
}
