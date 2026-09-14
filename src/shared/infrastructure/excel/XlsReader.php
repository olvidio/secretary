<?php

declare(strict_types=1);

namespace src\shared\infrastructure\excel;

use RuntimeException;

/**
 * Lector mínimo de la primera hoja de un .xls (BIFF8, OLE).
 * Sin dependencias externas; suficiente para extractos tabulares (p. ej. CaixaBank).
 */
final class XlsReader
{
    private const SIG = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    private string $data;

    private int $sectorSize = 512;

    private int $miniSectorSize = 64;

    private int $miniCutoff = 4096;

    /** @var array<int, int> */
    private array $fat = [];

    /** @var array<int, int> */
    private array $miniFat = [];

    /** @var list<string> */
    private array $sst = [];

    /** @var array<int, array<int, mixed>> */
    private array $grid = [];

    public function __construct(string $path)
    {
        if (!is_readable($path)) {
            throw new RuntimeException('No se puede leer ' . $path);
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            throw new RuntimeException('El Excel está vacío');
        }
        if (!str_starts_with($raw, self::SIG)) {
            throw new RuntimeException('No es un Excel .xls válido');
        }
        $this->data = $raw;
        $this->sectorSize = 1 << $this->u16($raw, 0x1E);
        $this->miniSectorSize = 1 << $this->u16($raw, 0x20);
        $this->miniCutoff = $this->u32($raw, 0x38);
        $this->fat = $this->construirFat();
        $this->miniFat = $this->construirMiniFat();
        $workbook = $this->stream('Workbook') ?? $this->stream('Book');
        if ($workbook === null) {
            throw new RuntimeException('No se encontró la hoja de cálculo en el Excel');
        }
        $this->parseWorkbook($workbook);
        if ($this->grid === []) {
            throw new RuntimeException('La hoja Excel no tiene datos');
        }
    }

    /**
     * @return list<list<string>>
     */
    public function filasComoTexto(): array
    {
        $maxRow = max(array_keys($this->grid));
        $maxCol = 0;
        foreach ($this->grid as $cols) {
            if ($cols !== []) {
                $maxCol = max($maxCol, max(array_keys($cols)));
            }
        }
        $out = [];
        for ($r = 0; $r <= $maxRow; $r++) {
            $fila = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $fila[] = $this->comoTexto($this->grid[$r][$c] ?? '');
            }
            $out[] = $fila;
        }

        return $out;
    }

    private function comoTexto(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (is_string($valor)) {
            return trim($valor);
        }
        if (!is_int($valor) && !is_float($valor)) {
            return trim((string) $valor);
        }
        $n = (float) $valor;
        if ($this->pareceFechaExcel($n)) {
            return ExcelDate::fromSerial($n)->format('Y-m-d');
        }
        if (abs($n - round($n)) < 0.0000001) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(sprintf('%.10F', $n), '0'), '.');
    }

    private function pareceFechaExcel(float $n): bool
    {
        return $n >= 20000 && $n < 60000 && abs($n - round($n)) < 0.0000001;
    }

    private function stream(string $nombre): ?string
    {
        foreach ($this->directorio() as $entry) {
            if ($entry['type'] !== 2) {
                continue;
            }
            if (strcasecmp($entry['name'], $nombre) !== 0) {
                continue;
            }

            return $this->leerEntrada($entry['start'], $entry['size']);
        }

        return null;
    }

    /**
     * @return list<array{name:string, type:int, start:int, size:int}>
     */
    private function directorio(): array
    {
        $firstDir = $this->u32($this->data, 0x30);
        $raw = $this->leerCadena($firstDir);
        $out = [];
        $n = intdiv(strlen($raw), 128);
        for ($i = 0; $i < $n; $i++) {
            $off = $i * 128;
            $nameLen = $this->u16($raw, $off + 0x40);
            if ($nameLen < 2) {
                continue;
            }
            $nombre = $this->utf16Le(substr($raw, $off, $nameLen - 2));
            $out[] = [
                'name' => $nombre,
                'type' => ord($raw[$off + 0x42]),
                'start' => $this->u32($raw, $off + 0x74),
                'size' => $this->u32($raw, $off + 0x78),
            ];
        }

        return $out;
    }

    private function leerEntrada(int $start, int $size): string
    {
        if ($size >= $this->miniCutoff || $start < 0) {
            return $this->leerCadena($start, $size);
        }
        $root = $this->rootEntry();
        if ($root === null) {
            return $this->leerCadena($start, $size);
        }
        $rootData = $this->leerCadena($root['start'], $root['size']);
        $out = '';
        $sector = $start;
        $guard = 0;
        while ($sector >= 0 && isset($this->miniFat[$sector]) && $guard++ < 100000) {
            $off = $sector * $this->miniSectorSize;
            $out .= substr($rootData, $off, $this->miniSectorSize);
            if (strlen($out) >= $size) {
                return substr($out, 0, $size);
            }
            $sector = $this->miniFat[$sector];
        }

        return substr($out, 0, $size);
    }

    /** @return array{name:string, type:int, start:int, size:int}|null */
    private function rootEntry(): ?array
    {
        foreach ($this->directorio() as $entry) {
            if ($entry['type'] === 5) {
                return $entry;
            }
        }

        return null;
    }

    private function leerCadena(int $startSector, ?int $size = null): string
    {
        $out = '';
        $sector = $startSector;
        $guard = 0;
        while ($sector >= 0 && $sector < 0xFFFFFFFE && isset($this->fat[$sector]) && $guard++ < 100000) {
            $off = ($sector + 1) * $this->sectorSize;
            $out .= substr($this->data, $off, $this->sectorSize);
            if ($size !== null && strlen($out) >= $size) {
                return substr($out, 0, $size);
            }
            $next = $this->fat[$sector];
            if ($next === $sector) {
                break;
            }
            $sector = $next;
        }

        return $size !== null ? substr($out, 0, $size) : $out;
    }

    /** @return array<int, int> */
    private function construirFat(): array
    {
        $fat = [];
        $porSector = intdiv($this->sectorSize, 4);
        $sectoresFat = [];
        for ($i = 0; $i < 109; $i++) {
            $sec = $this->u32($this->data, 0x4C + $i * 4);
            if ($sec < 0xFFFFFFFE) {
                $sectoresFat[] = $sec;
            }
        }
        $extraStart = $this->u32($this->data, 0x44);
        $extraCount = $this->u32($this->data, 0x48);
        $sector = $extraStart;
        $guard = 0;
        while ($sector >= 0 && $sector < 0xFFFFFFFE && $extraCount > 0 && $guard++ < 100000) {
            $off = ($sector + 1) * $this->sectorSize;
            for ($i = 0; $i < $porSector - 1; $i++) {
                $ref = $this->u32($this->data, $off + $i * 4);
                if ($ref < 0xFFFFFFFE) {
                    $sectoresFat[] = $ref;
                }
            }
            $sector = $this->u32($this->data, $off + ($porSector - 1) * 4);
            $extraCount--;
        }
        $idx = 0;
        foreach ($sectoresFat as $fatSec) {
            $off = ($fatSec + 1) * $this->sectorSize;
            for ($j = 0; $j < $porSector; $j++) {
                $fat[$idx++] = $this->u32($this->data, $off + $j * 4);
            }
        }

        return $fat;
    }

    /** @return array<int, int> */
    private function construirMiniFat(): array
    {
        $start = $this->u32($this->data, 0x3C);
        $count = $this->u32($this->data, 0x40);
        if ($start >= 0xFFFFFFFE || $count === 0) {
            return [];
        }
        $raw = $this->leerCadena($start, $count * 4);
        $out = [];
        $n = intdiv(strlen($raw), 4);
        for ($i = 0; $i < $n; $i++) {
            $out[$i] = $this->u32($raw, $i * 4);
        }

        return $out;
    }

    private function parseWorkbook(string $data): void
    {
        $pos = 0;
        $len = strlen($data);
        $sheetOffset = null;
        while ($pos + 4 <= $len) {
            $rec = $this->u16($data, $pos);
            $size = $this->u16($data, $pos + 2);
            $pos += 4;
            if ($pos + $size > $len) {
                break;
            }
            $payload = substr($data, $pos, $size);
            $pos += $size;
            if ($rec === 0x0085 && $sheetOffset === null) {
                $sheetOffset = $this->u32($payload, 0);
            } elseif ($rec === 0x00FC) {
                $this->sst = $this->leerSst($payload, $data, $pos);
            } elseif ($rec === 0x000A && $sheetOffset !== null) {
                break;
            }
        }
        if ($sheetOffset === null) {
            throw new RuntimeException('Hoja Excel no encontrada');
        }
        $this->parseSheet(substr($data, $sheetOffset));
    }

    private function parseSheet(string $data): void
    {
        $pos = 0;
        $len = strlen($data);
        while ($pos + 4 <= $len) {
            $rec = $this->u16($data, $pos);
            $size = $this->u16($data, $pos + 2);
            $pos += 4;
            if ($pos + $size > $len) {
                break;
            }
            $payload = substr($data, $pos, $size);
            $pos += $size;
            if ($rec === 0x000A) {
                break;
            }
            if ($rec === 0x0203 && $size >= 14) {
                $row = $this->u16($payload, 0);
                $col = $this->u16($payload, 2);
                $val = unpack('d', substr($payload, 6, 8));
                $this->grid[$row][$col] = $val[1] ?? 0.0;
            } elseif ($rec === 0x027E && $size >= 10) {
                $row = $this->u16($payload, 0);
                $col = $this->u16($payload, 2);
                $this->grid[$row][$col] = $this->decodeRk($this->u32($payload, 6));
            } elseif ($rec === 0x00BD && $size >= 6) {
                $row = $this->u16($payload, 0);
                $col = $this->u16($payload, 2);
                $p = 4;
                while ($p + 6 <= $size) {
                    $this->grid[$row][$col] = $this->decodeRk($this->u32($payload, $p + 2));
                    $col++;
                    $p += 6;
                }
            } elseif ($rec === 0x00FD && $size >= 10) {
                $row = $this->u16($payload, 0);
                $col = $this->u16($payload, 2);
                $idx = $this->u32($payload, 6);
                $this->grid[$row][$col] = $this->sst[$idx] ?? '';
            } elseif ($rec === 0x0204 && $size >= 8) {
                $row = $this->u16($payload, 0);
                $col = $this->u16($payload, 2);
                $strLen = $this->u16($payload, 6);
                $this->grid[$row][$col] = substr($payload, 8, $strLen);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function leerSst(string $payload, string $data, int $posAfter): array
    {
        $total = $this->u32($payload, 4);
        $out = [];
        $p = 8;
        $len = strlen($payload);
        while (count($out) < $total && $p < $len) {
            if ($p + 3 > $len) {
                break;
            }
            $charCount = $this->u16($payload, $p);
            $flags = ord($payload[$p + 2]);
            $p += 3;
            $wide = ($flags & 1) === 1;
            if (($flags & 8) === 8) {
                $p += 2;
            }
            if (($flags & 4) === 4) {
                $p += 4;
            }
            $byteLen = $wide ? $charCount * 2 : $charCount;
            if ($p + $byteLen > $len) {
                $faltan = $byteLen - ($len - $p);
                $texto = $wide
                    ? $this->utf16Le(substr($payload, $p))
                    : substr($payload, $p);
                $resto = $this->continuarSst($data, $posAfter, $wide, $faltan);
                $out[] = $texto . $resto['text'];
                $posAfter = $resto['pos'];
                break;
            }
            $chunk = substr($payload, $p, $byteLen);
            $p += $byteLen;
            $out[] = $wide ? $this->utf16Le($chunk) : $chunk;
        }

        return $out;
    }

    /**
     * @return array{text:string, pos:int}
     */
    private function continuarSst(string $data, int $pos, bool $wide, int $faltanBytes): array
    {
        $texto = '';
        $len = strlen($data);
        while ($pos + 4 <= $len && $faltanBytes > 0) {
            $rec = $this->u16($data, $pos);
            $size = $this->u16($data, $pos + 2);
            $pos += 4;
            if ($rec !== 0x003C) {
                $pos += $size;
                continue;
            }
            $payload = substr($data, $pos, $size);
            $pos += $size;
            $p = 0;
            if ($texto === '' && !$wide && $size > 0) {
                $wide = (ord($payload[0]) & 1) === 1;
                $p = 1;
            }
            $take = min($faltanBytes, $size - $p);
            $chunk = substr($payload, $p, $take);
            $texto .= $wide ? $this->utf16Le($chunk) : $chunk;
            $faltanBytes -= $take;
        }

        return ['text' => $texto, 'pos' => $pos];
    }

    private function decodeRk(int $rk): float|int
    {
        $isInt = ($rk & 2) !== 0;
        $isX100 = ($rk & 1) !== 0;
        if ($isInt) {
            $val = ($rk & 0xFFFFFFFC) >> 2;
            if ($val & 0x20000000) {
                $val -= 0x40000000;
            }
        } else {
            $bytes = pack('V', $rk & 0xFFFFFFFC) . "\0\0\0\0";
            $val = unpack('d', $bytes)[1];
        }
        if ($isX100) {
            $val /= 100;
        }

        return $val;
    }

    private function utf16Le(string $bytes): string
    {
        $conv = @iconv('UTF-16LE', 'UTF-8//IGNORE', $bytes);

        return is_string($conv) ? $conv : $bytes;
    }

    private function u16(string $data, int $off): int
    {
        return unpack('v', substr($data, $off, 2))[1];
    }

    private function u32(string $data, int $off): int
    {
        $v = unpack('V', substr($data, $off, 4))[1];

        return $v < 0 ? $v + 0x100000000 : $v;
    }
}
