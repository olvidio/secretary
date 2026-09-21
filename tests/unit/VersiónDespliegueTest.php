<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\shared\infrastructure\VersiónDespliegue;

final class VersiónDespliegueTest extends TestCase
{
    public function testLeeEtiquetaDesdeVersionJson(): void
    {
        $directorio = sys_get_temp_dir() . '/secretary-version-' . uniqid('', true);
        mkdir($directorio . '/var', 0775, true);
        file_put_contents($directorio . '/var/version.json', json_encode(['version' => 'v9.9.9'], JSON_THROW_ON_ERROR));

        $servicio = new VersiónDespliegue($directorio);
        self::assertSame('v9.9.9', $servicio->etiqueta());

        @unlink($directorio . '/var/version.json');
        @rmdir($directorio . '/var');
        @rmdir($directorio);
    }

    public function testSinVersionJsonDevuelveDesarrollo(): void
    {
        $directorio = sys_get_temp_dir() . '/secretary-version-' . uniqid('', true);
        mkdir($directorio, 0775, true);

        $servicio = new VersiónDespliegue($directorio);
        self::assertSame('desarrollo', $servicio->etiqueta());

        @rmdir($directorio);
    }
}
