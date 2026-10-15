<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\cierre\domain\services\ImporteSaldableDeuda;
use src\cierre\domain\services\SuperavitVivienda;

final class ImporteSaldableDeudaTest extends TestCase
{
    public function testPrimeroElSuperavitYLoQueFaltaDelSaldo(): void
    {
        $partes = ImporteSaldableDeuda::partes(-100000, 40000, 70000);
        self::assertSame(40000, $partes['superavit']);
        self::assertSame(60000, $partes['saldo']);
        self::assertSame(100000, $partes['total']);
    }

    public function testElSuperavitCubreLaDeudaSinTocarElSaldo(): void
    {
        $partes = ImporteSaldableDeuda::partes(-82936, 243388, 0);
        self::assertSame(82936, $partes['superavit']);
        self::assertSame(0, $partes['saldo']);
    }

    public function testSinSuperavitNiSaldoNoHayNadaQueSaldar(): void
    {
        $partes = ImporteSaldableDeuda::partes(-296102, 0, 0);
        self::assertSame(0, $partes['total']);
        self::assertSame(0, ImporteSaldableDeuda::partes(-296102, 0, -100)['total']);
        self::assertSame(0, ImporteSaldableDeuda::partes(0, 243388, 50000)['total']);
    }

    public function testSuperavitEsLoIngresadoPorEncimaDeLaCuota(): void
    {
        self::assertSame(243388, SuperavitVivienda::cents(1200000, 956612));
        self::assertSame(0, SuperavitVivienda::cents(0, 956612));
        self::assertSame(0, SuperavitVivienda::cents(100, 100));
    }

    public function testDeshaceLaAplicacionYaAnotadaParaRecalcular(): void
    {
        self::assertSame(-82936, SuperavitVivienda::deudaSinAplicar(0, 82936));
        self::assertSame(-82936, SuperavitVivienda::deudaSinAplicar(-82936, 0));
    }
}
