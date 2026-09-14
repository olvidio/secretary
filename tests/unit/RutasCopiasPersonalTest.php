<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\personal\infrastructure\persistence\AlmacenCopiasPersonal;
use src\personal\infrastructure\persistence\RutasCopiasPersonal;

final class RutasCopiasPersonalTest extends TestCase
{
    public function testDirectorioPorDefecto(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertSame($root . '/var/backups/personal', RutasCopiasPersonal::directorio());
    }

    public function testAlmacenSoloAceptaFicherosDeLaPersona(): void
    {
        $dir = sys_get_temp_dir() . '/sec_test_copias_' . getmypid();
        @mkdir($dir, 0777, true);
        $almacen = new AlmacenCopiasPersonal($dir, 9, 'aa');
        file_put_contents($dir . '/personal_9_aa_20260101_120000.json', '{}');
        file_put_contents($dir . '/personal_10_bb_20260101_120000.json', '{}');
        self::assertCount(1, $almacen->listar());
        $this->expectException(\InvalidArgumentException::class);
        $almacen->rutaDeNombre('personal_10_bb_20260101_120000.json');
        array_map('unlink', glob($dir . '/*') ?: []);
        @rmdir($dir);
    }
}
