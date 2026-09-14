<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use src\shared\infrastructure\persistence\PostgresDumper;

final class PostgresDumperTest extends TestCase
{
    public function testParsePgsqlDsn(): void
    {
        $parsed = PostgresDumper::parsePgsqlDsn('pgsql:host=127.0.0.1;port=5455;dbname=secretario');
        self::assertSame('127.0.0.1', $parsed['host']);
        self::assertSame(5455, $parsed['port']);
        self::assertSame('secretario', $parsed['dbname']);
    }

    public function testParsePgsqlDsnPuertoPorDefecto(): void
    {
        $parsed = PostgresDumper::parsePgsqlDsn('pgsql:host=db;dbname=secretario');
        self::assertSame('db', $parsed['host']);
        self::assertSame(5432, $parsed['port']);
    }

    public function testParsePgsqlDsnRechazaVacio(): void
    {
        $this->expectException(RuntimeException::class);
        PostgresDumper::parsePgsqlDsn('');
    }
}
