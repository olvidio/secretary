<?php

declare(strict_types=1);

namespace Tests\unit;

use PHPUnit\Framework\TestCase;
use src\shared\infrastructure\excel\ExcelDate;

final class ExcelDateTest extends TestCase
{
    public function testSerial2026(): void
    {
        self::assertSame('2026-01-01', ExcelDate::fromSerial(46023)->format('Y-m-d'));
        self::assertSame('2026-06-30', ExcelDate::fromSerial(46203)->format('Y-m-d'));
        self::assertSame('2026-01-15', ExcelDate::parseCell('15/01/2026')?->format('Y-m-d'));
    }
}
