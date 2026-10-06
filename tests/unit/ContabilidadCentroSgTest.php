<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\plan\domain\services\CatalogoPlanesContables;
use src\plan\domain\services\ContabilidadCentroSg;

final class ContabilidadCentroSgTest extends TestCase
{
    public function testH16sNoAdmiteTraspasoCajaBanco(): void
    {
        self::assertFalse(ContabilidadCentroSg::admiteTraspasoCajaBanco(CatalogoPlanesContables::CENTRO_SG));
    }

    public function testH16nSiAdmiteTraspasoCajaBanco(): void
    {
        self::assertTrue(ContabilidadCentroSg::admiteTraspasoCajaBanco(CatalogoPlanesContables::H16N));
    }

    public function testCodigosDestino(): void
    {
        self::assertTrue(ContabilidadCentroSg::esCodigoDestino('41'));
        self::assertTrue(ContabilidadCentroSg::esCodigoDestino('42'));
        self::assertTrue(ContabilidadCentroSg::esCodigoDestino('54'));
        self::assertFalse(ContabilidadCentroSg::esCodigoDestino('21'));
    }
}
