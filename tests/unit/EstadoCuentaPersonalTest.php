<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\informes\domain\services\EstadoCuentaPersonal;

final class EstadoCuentaPersonalTest extends TestCase
{
    public function testCuadradaYACeroEstaBien(): void
    {
        self::assertSame(EstadoCuentaPersonal::OK, EstadoCuentaPersonal::de(true, 0));
    }

    /** El caso de JMG: apuntes A cuadrados y 406,47 € de sobrante de remesas. */
    public function testPositivaConApuntesCuadradosEsSobrantePorDestinar(): void
    {
        self::assertSame(EstadoCuentaPersonal::POR_DESTINAR, EstadoCuentaPersonal::de(true, 40647));
    }

    public function testNegativaOApuntesSinCuadrarHayQueRevisarla(): void
    {
        self::assertSame(EstadoCuentaPersonal::REVISAR, EstadoCuentaPersonal::de(true, -100));
        self::assertSame(EstadoCuentaPersonal::REVISAR, EstadoCuentaPersonal::de(false, 40647));
        self::assertSame(EstadoCuentaPersonal::REVISAR, EstadoCuentaPersonal::de(false, 0));
    }
}
