<?php

declare(strict_types=1);

namespace src\personal\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\personal\domain\contracts\LectorExtractoEnFilas;
use src\personal\domain\value_objects\LineaExtractoBanco;
use src\shared\domain\value_objects\Dinero;

/**
 * Extracto CaixaBank (CaixaBankNow: Extraer movimientos en Excel o CSV).
 * Cabeceras en castellano o catalán. Suele ir con punto y coma y decimales con coma.
 */
final class LectorCsvCaixaBank implements LectorExtractoEnFilas
{
    public function leer(string $contenido): array
    {
        $contenido = $this->aUtf8($this->sinBom($contenido));
        if (trim($contenido) === '') {
            throw new InvalidArgumentException('El fichero CSV está vacío');
        }
        $delim = $this->delimitador($contenido);
        $filas = $this->filas($contenido, $delim);
        if ($filas === []) {
            throw new InvalidArgumentException('El fichero CSV está vacío');
        }

        return $this->leerFilas($filas);
    }

    /**
     * @param list<list<string>> $filas
     * @return list<LineaExtractoBanco>
     */
    public function leerFilas(array $filas): array
    {
        if ($filas === []) {
            throw new InvalidArgumentException('El extracto está vacío');
        }
        $filas = array_map(
            fn (array $cols) => array_map(fn ($c) => $this->aUtf8((string) $c), $cols),
            $filas,
        );
        $idx = null;
        $desde = 0;
        foreach ($filas as $i => $cols) {
            if ($this->filaVacia($cols)) {
                continue;
            }
            $candidato = $this->indices($cols);
            if ($candidato !== null) {
                $idx = $candidato;
                $desde = $i + 1;
                break;
            }
        }
        if ($idx === null) {
            throw new InvalidArgumentException(
                'Este fichero no parece un extracto CaixaBank. Compruebe el banco origen.'
            );
        }
        $out = [];
        $n = count($filas);
        for ($i = $desde; $i < $n; $i++) {
            $cols = $filas[$i];
            if ($this->filaVacia($cols)) {
                continue;
            }
            $linea = $this->linea($cols, $idx, $i + 1);
            if ($linea !== null) {
                $out[] = $linea;
            }
        }
        if ($out === []) {
            throw new InvalidArgumentException('No hay movimientos en el extracto');
        }

        return $out;
    }

    /**
     * @param list<string> $cabecera
     * @return array{fecha:int, movimiento:int, extra:int, importe:int}|null
     */
    private function indices(array $cabecera): ?array
    {
        $map = [];
        foreach ($cabecera as $i => $nombre) {
            $clave = $this->normalizar($nombre);
            if ($clave !== '') {
                $map[$clave] = $i;
            }
        }
        $fecha = $this->buscar($map, ['fecha', 'data', 'date']);
        if ($fecha === null) {
            $fecha = $this->buscar($map, ['fecha valor', 'data valor', 'value date']);
        }
        $mov = $this->buscar($map, [
            'movimiento', 'moviment', 'concepto', 'concepte', 'descripcion', 'descripcio',
        ]);
        $importe = $this->buscar($map, ['importe', 'import', 'amount']);
        if ($fecha === null || $mov === null || $importe === null) {
            return null;
        }

        return [
            'fecha' => $fecha,
            'movimiento' => $mov,
            'extra' => $this->buscar($map, ['mas datos', 'mes dades', 'mes datos', 'detalle', 'detall']) ?? -1,
            'importe' => $importe,
        ];
    }

    /**
     * @param list<string> $cols
     * @param array{fecha:int, movimiento:int, extra:int, importe:int} $idx
     */
    private function linea(array $cols, array $idx, int $n): ?LineaExtractoBanco
    {
        $fechaRaw = $this->celda($cols, $idx['fecha']);
        $mov = $this->celda($cols, $idx['movimiento']);
        $extra = $idx['extra'] >= 0 ? $this->celda($cols, $idx['extra']) : '';
        $importeRaw = $this->celda($cols, $idx['importe']);
        if ($fechaRaw === '' && $mov === '' && $importeRaw === '') {
            return null;
        }
        if ($this->normalizar($fechaRaw) === 'fecha' || $this->normalizar($mov) === 'movimiento') {
            return null;
        }
        try {
            $fecha = $this->fecha($fechaRaw);
            $dinero = $this->importe($importeRaw);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Fila ' . $n . ': ' . $e->getMessage());
        }
        if ($dinero->isZero()) {
            return null;
        }
        $partes = [];
        if ($mov !== '') {
            $partes[] = $mov;
        }
        if ($extra !== '' && $extra !== $mov) {
            $partes[] = $extra;
        }
        $concepto = $partes !== [] ? implode(' · ', $partes) : 'Movimiento CaixaBank';
        $huella = hash('sha256', implode('|', [
            'caixabank',
            $fecha,
            (string) $dinero->toCents(),
            mb_strtolower($mov),
            mb_strtolower($extra),
        ]));

        return new LineaExtractoBanco($fecha, $dinero->toCents(), $concepto, $huella);
    }

    private function fecha(string $raw): string
    {
        $raw = trim($raw);
        foreach (['Y-m-d', 'd/m/Y', 'd.m.Y', 'd-m-Y'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $raw);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('Y-m-d');
            }
        }
        throw new InvalidArgumentException('Fecha no reconocida: ' . $raw);
    }

    private function importe(string $raw): Dinero
    {
        $s = trim(str_replace(["\u{00A0}", ' ', '€'], '', $raw));
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

    private function celda(array $cols, int $i): string
    {
        if ($i < 0 || !isset($cols[$i])) {
            return '';
        }

        return trim((string) $cols[$i]);
    }

    /** @param array<string, int> $map */
    private function buscar(array $map, array $nombres): ?int
    {
        foreach ($nombres as $n) {
            $clave = $this->normalizar($n);
            if ($clave !== '' && isset($map[$clave])) {
                return $map[$clave];
            }
        }

        return null;
    }

    private function normalizar(string $s): string
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

    private function aUtf8(string $s): string
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

    private function sinBom(string $s): string
    {
        if (str_starts_with($s, "\xEF\xBB\xBF")) {
            return substr($s, 3);
        }

        return $s;
    }

    private function delimitador(string $contenido): string
    {
        $puntos = substr_count($contenido, ';');
        $comas = substr_count($contenido, ',');
        if ($puntos > $comas) {
            return ';';
        }

        return ',';
    }

    /** @return list<list<string>> */
    private function filas(string $contenido, string $delim): array
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
    private function filaVacia(array $cols): bool
    {
        foreach ($cols as $c) {
            if (trim($c) !== '') {
                return false;
            }
        }

        return true;
    }
}
