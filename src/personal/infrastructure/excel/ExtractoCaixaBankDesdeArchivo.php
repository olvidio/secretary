<?php

declare(strict_types=1);

namespace src\personal\infrastructure\excel;

use InvalidArgumentException;
use src\importacion\infrastructure\excel\XlsxReader;
use src\shared\infrastructure\excel\ExcelDate;
use src\shared\infrastructure\excel\XlsReader;

/** Lee un Excel de CaixaBank (.xls / .xlsx) como tabla de celdas de texto. */
final class ExtractoCaixaBankDesdeArchivo
{
    /**
     * @return list<list<string>>
     */
    public static function filas(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'xls' => (new XlsReader($path))->filasComoTexto(),
            'xlsx' => self::filasXlsx($path),
            default => throw new InvalidArgumentException('Formato Excel no soportado: use .xls o .xlsx'),
        };
    }

    /**
     * @return list<list<string>>
     */
    private static function filasXlsx(string $path): array
    {
        $book = new XlsxReader($path);
        $nombres = $book->sheetNames();
        if ($nombres === []) {
            throw new InvalidArgumentException('El Excel no tiene hojas');
        }
        $grid = $book->sheet($nombres[0]);
        if ($grid === []) {
            throw new InvalidArgumentException('La hoja Excel está vacía');
        }
        $maxRow = max(array_keys($grid));
        $colsUsadas = [];
        foreach ($grid as $cols) {
            foreach (array_keys($cols) as $col) {
                $colsUsadas[$col] = true;
            }
        }
        if ($colsUsadas === []) {
            throw new InvalidArgumentException('La hoja Excel está vacía');
        }
        $letras = array_keys($colsUsadas);
        sort($letras);
        $primera = $letras[0];
        $ultima = $letras[count($letras) - 1];
        $out = [];
        for ($r = 1; $r <= $maxRow; $r++) {
            $fila = [];
            for ($c = self::colIndex($primera); $c <= self::colIndex($ultima); $c++) {
                $letra = self::colLetra($c);
                $fila[] = self::comoTexto($grid[$r][$letra] ?? '');
            }
            $out[] = $fila;
        }

        return $out;
    }

    private static function comoTexto(mixed $valor): string
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
        $fecha = ExcelDate::parseCell($n);
        if ($fecha !== null && $n >= 20000 && abs($n - round($n)) < 0.0000001) {
            return $fecha->format('Y-m-d');
        }
        if (abs($n - round($n)) < 0.0000001) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(sprintf('%.10F', $n), '0'), '.');
    }

    private static function colIndex(string $letra): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letra)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }

    private static function colLetra(int $index): string
    {
        $s = '';
        while ($index > 0) {
            $index--;
            $s = chr(65 + ($index % 26)) . $s;
            $index = intdiv($index, 26);
        }

        return $s;
    }
}
