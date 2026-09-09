<?php

declare(strict_types=1);

namespace src\shared\infrastructure\excel;

use DateTimeImmutable;

final class ExcelDate
{
    public static function fromSerial(int|float|string $serial): DateTimeImmutable
    {
        $n = (int) round((float) $serial);
        $base = new DateTimeImmutable('1899-12-30');
        return $base->modify('+' . $n . ' days');
    }

    public static function parseCell(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if (is_numeric($value) && (float) $value > 20000) {
            return self::fromSerial($value);
        }
        $s = trim((string) $value);
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $s, $m) === 1) {
            return DateTimeImmutable::createFromFormat('!d/m/Y', $s) ?: null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s) === 1) {
            return new DateTimeImmutable(substr($s, 0, 10));
        }

        return null;
    }
}
