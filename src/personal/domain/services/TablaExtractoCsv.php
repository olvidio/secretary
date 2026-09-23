<?php

declare(strict_types=1);

namespace src\personal\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\shared\domain\value_objects\Dinero;

/** Lectura mecánica de un CSV de extracto español (cabecera, fechas, importes). */
trait TablaExtractoCsv
{
    protected function aUtf8(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        if (mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }
        $conv = @iconv('Windows-1252', 'UTF-8//IGNORE', $s);

        return is_string($conv) && $conv !== '' ? $conv : $s;
    }

    protected function sinBom(string $s): string
    {
        if (str_starts_with($s, "\xEF\xBB\xBF")) {
            return substr($s, 3);
        }

        return $s;
    }

    protected function delimitador(string $contenido): string
    {
        $puntos = substr_count($contenido, ';');
        $comas = substr_count($contenido, ',');
        if ($puntos > $comas) {
            return ';';
        }

        return ',';
    }

    /**
     * @return list<list<string>>
     */
    protected function filasCsv(string $contenido, string $delim): array
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new InvalidArgumentException('No se pudo leer el CSV');
        }
        fwrite($fh, $contenido);
        rewind($fh);
        $out = [];
        while (($row = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = array_map(static fn ($c) => is_string($c) ? $c : (string) $c, $row);
        }
        fclose($fh);

        return $out;
    }

    /** @param list<string> $cols */
    protected function filaVacia(array $cols): bool
    {
        foreach ($cols as $c) {
            if (trim($c) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $cabecera
     * @return array<string, int>
     */
    protected function mapaCabecera(array $cabecera): array
    {
        $map = [];
        foreach ($cabecera as $i => $nombre) {
            $clave = $this->normalizarCabecera($nombre);
            if ($clave !== '') {
                $map[$clave] = $i;
            }
        }

        return $map;
    }

    /** @param array<string, int> $map @param list<string> $nombres */
    protected function buscarColumna(array $map, array $nombres): ?int
    {
        foreach ($nombres as $n) {
            $clave = $this->normalizarCabecera($n);
            if ($clave !== '' && isset($map[$clave])) {
                return $map[$clave];
            }
        }

        return null;
    }

    /** @param list<string> $cols */
    protected function celda(array $cols, int $i): string
    {
        if ($i < 0 || !isset($cols[$i])) {
            return '';
        }

        return trim((string) $cols[$i]);
    }

    protected function fechaExtracto(string $raw): string
    {
        $raw = trim($raw);
        foreach (['Y-m-d', 'd/m/Y', 'd.m.Y', 'd-m-Y', 'd/m/y'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $raw);
            if ($dt instanceof DateTimeImmutable) {
                $errores = DateTimeImmutable::getLastErrors();
                if ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0)) {
                    continue;
                }

                return $dt->format('Y-m-d');
            }
        }
        throw new InvalidArgumentException('Fecha no reconocida: ' . $raw);
    }

    protected function importeExtracto(string $raw): Dinero
    {
        $s = trim(str_replace(["\u{00A0}", ' ', '€', 'EUR', 'Euro', 'EURO'], '', $raw));
        if ($s === '') {
            throw new InvalidArgumentException('Falta la cantidad');
        }
        $neg = str_starts_with($s, '-') || str_starts_with($s, '(');
        $s = trim($s, "+- \t()");
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $ultimoComa = strrpos($s, ',');
            $ultimoPunto = strrpos($s, '.');
            if ($ultimoComa > $ultimoPunto) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }
        $dinero = Dinero::fromInput($s);

        return $neg ? $dinero->neg() : $dinero;
    }

    protected function normalizarCabecera(string $s): string
    {
        $s = trim($s);
        $s = str_replace("\xEF\xBB\xBF", '', $s);
        $s = strtr($s, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'Ç' => 'C', 'ç' => 'c',
        ]);
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($trans) && $trans !== '') {
            $s = $trans;
        }
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }
}
