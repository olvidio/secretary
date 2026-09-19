<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\personal\infrastructure\persistence\AlmacenCopiasPersonal;
use src\shared\application\AsegurarHuecoCopias;
use src\shared\domain\exceptions\LimiteCopiasAlcanzado;
use src\shared\domain\services\PoliticaCopiasServidor;

final class AsegurarHuecoCopiasTest extends TestCase
{
    public function testNombreMasAntiguaUsaLaFechaMasTemprana(): void
    {
        $nombre = PoliticaCopiasServidor::nombreMasAntigua([
            ['filename' => 'nueva.sql', 'fecha' => '2026-09-19 10:00:00'],
            ['filename' => 'vieja.sql', 'fecha' => '2026-01-01 08:00:00'],
            ['filename' => 'media.sql', 'fecha' => '2026-06-01 12:00:00'],
        ]);
        self::assertSame('vieja.sql', $nombre);
    }

    public function testNoHaceNadaSiHayMenosDelMaximo(): void
    {
        $borrados = [];
        AsegurarHuecoCopias::ejecutar(
            $this->copias(4),
            static function (string $nombre) use (&$borrados): void {
                $borrados[] = $nombre;
            },
            false,
        );
        self::assertSame([], $borrados);
    }

    public function testLanzaSiEstaLlenoYNoSePideBorrar(): void
    {
        $this->expectException(LimiteCopiasAlcanzado::class);
        $this->expectExceptionMessage('Solo se permite tener 5 copias en el servidor');
        AsegurarHuecoCopias::ejecutar($this->copias(5), static fn (string $n): null => null, false);
    }

    public function testBorraLaMasAntiguaSiSePide(): void
    {
        $borrados = [];
        AsegurarHuecoCopias::ejecutar(
            $this->copias(5),
            static function (string $nombre) use (&$borrados): void {
                $borrados[] = $nombre;
            },
            true,
        );
        self::assertSame(['copia-1.json'], $borrados);
    }

    public function testBorraHastaQuedarPorDebajoDelMaximo(): void
    {
        $borrados = [];
        AsegurarHuecoCopias::ejecutar(
            $this->copias(7),
            static function (string $nombre) use (&$borrados): void {
                $borrados[] = $nombre;
            },
            true,
        );
        self::assertSame(['copia-1.json', 'copia-2.json', 'copia-3.json'], $borrados);
    }

    public function testConAlmacenPersonalBorraElFicheroMasAntiguo(): void
    {
        $dir = sys_get_temp_dir() . '/sec_test_limite_copias_' . getmypid();
        @mkdir($dir, 0777, true);
        $almacen = new AlmacenCopiasPersonal($dir, 3, 'zz');
        try {
            for ($i = 1; $i <= 5; $i++) {
                $nombre = sprintf('personal_3_zz_2026010%d_120000.json', $i);
                $ruta = $dir . '/' . $nombre;
                file_put_contents($ruta, '{}');
                touch($ruta, strtotime('2026-01-0' . $i . ' 12:00:00'));
            }
            self::assertCount(5, $almacen->listar());
            AsegurarHuecoCopias::ejecutar(
                $almacen->listar(),
                fn (string $nombre) => $almacen->borrarPorNombre($nombre),
                true,
            );
            $quedan = array_column($almacen->listar(), 'filename');
            self::assertCount(4, $quedan);
            self::assertNotContains('personal_3_zz_20260101_120000.json', $quedan);
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
        }
    }

    /**
     * @return list<array{filename: string, fecha: string}>
     */
    private function copias(int $n): array
    {
        $filas = [];
        for ($i = 1; $i <= $n; $i++) {
            $filas[] = [
                'filename' => 'copia-' . $i . '.json',
                'fecha' => sprintf('2026-01-%02d 10:00:00', $i),
            ];
        }

        return $filas;
    }
}
