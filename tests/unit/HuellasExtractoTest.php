<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\importacion\domain\services\HuellasExtracto;
use src\importacion\domain\services\LectorCsvCaixaBank;

final class HuellasExtractoTest extends TestCase
{
    public function testDosApuntesIgualesConservanLaPrimeraHuellaYNumeranElResto(): void
    {
        $csv = "Data;Data valor;Moviment;Més dades;Import;Saldo\n"
            . "01/09/2026;01/09/2026;SUBCOM PROP GARAT;Rebut de finques, lloguers;-96;2365,12\n"
            . "01/09/2026;01/09/2026;SUBCOM PROP GARAT;Rebut de finques, lloguers;-96;2461,12\n"
            . "04/09/2026;04/09/2026;CLUB NATACIO VIC;Rebuts varis;-28,23;2336,89\n";
        $leidas = (new LectorCsvCaixaBank())->leer($csv);
        self::assertSame($leidas[0]->huella, $leidas[1]->huella);

        $lineas = HuellasExtracto::distinguirIguales($leidas);
        self::assertCount(3, $lineas);
        self::assertSame($leidas[0]->huella, $lineas[0]->huella);
        self::assertSame($leidas[0]->huella . '#2', $lineas[1]->huella);
        self::assertSame(-9600, $lineas[0]->cents);
        self::assertSame(-9600, $lineas[1]->cents);
        self::assertSame($leidas[2]->huella, $lineas[2]->huella);
        self::assertNotSame($lineas[0]->huella, $lineas[1]->huella);
    }

    public function testElMismoFicheroVuelveADarLasMismasHuellas(): void
    {
        $csv = "Fecha;Movimiento;Importe\n"
            . "01/09/2026;BIZUM;-22\n"
            . "01/09/2026;BIZUM;-22\n"
            . "01/09/2026;BIZUM;-22\n";
        $lector = new LectorCsvCaixaBank();
        $a = HuellasExtracto::distinguirIguales($lector->leer($csv));
        $b = HuellasExtracto::distinguirIguales($lector->leer($csv));
        self::assertSame($a[0]->huella, $b[0]->huella);
        self::assertSame($a[0]->huella . '#2', $a[1]->huella);
        self::assertSame($a[0]->huella . '#3', $a[2]->huella);
        self::assertSame($a[1]->huella, $b[1]->huella);
        self::assertSame($a[2]->huella, $b[2]->huella);
    }
}
