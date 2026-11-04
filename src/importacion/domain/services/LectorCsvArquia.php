<?php

declare(strict_types=1);

namespace src\importacion\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\importacion\domain\contracts\LectorExtractoEnFilas;
use src\importacion\domain\contracts\ReconocedorExtractoBanco;
use src\importacion\domain\value_objects\LineaExtractoBanco;

/**
 * Extracto de Arquia Banca (Excel «movimientos»).
 *
 * Bloque inicial con Nombre / IBAN, fila de cabecera «Fecha de la operación · Fecha valor ·
 * Tipo movimiento · Importe · Saldo · Nro. Apunte». Las fechas llegan como número de serie
 * Excel con hora (p. ej. 46328.0416666667), de ahí que se acepte ese formato.
 */
final class LectorCsvArquia implements LectorExtractoEnFilas, ReconocedorExtractoBanco
{
    use TablaExtractoCsv;

    public function leer(string $contenido): array
    {
        $contenido = $this->aUtf8($this->sinBom($contenido));
        if (trim($contenido) === '') {
            throw new InvalidArgumentException('El fichero CSV está vacío');
        }
        $filas = $this->filasCsv($contenido, $this->delimitador($contenido));
        if ($filas === []) {
            throw new InvalidArgumentException('El fichero CSV está vacío');
        }

        return $this->leerFilas($filas);
    }

    public function leerFilas(array $filas): array
    {
        if ($filas === []) {
            throw new InvalidArgumentException('El extracto está vacío');
        }
        $filas = array_map(
            fn (array $cols) => array_map(fn ($c) => $this->aUtf8((string) $c), $cols),
            $filas,
        );
        $encontrada = $this->buscarCabecera($filas);
        if ($encontrada === null) {
            throw new InvalidArgumentException(
                'Este fichero no parece un extracto de Arquia. Compruebe el banco origen.'
            );
        }
        [$i0, $idx] = $encontrada;
        $out = [];
        $n = count($filas);
        for ($i = $i0 + 1; $i < $n; $i++) {
            if ($this->filaVacia($filas[$i])) {
                continue;
            }
            $linea = $this->linea($filas[$i], $idx, $i + 1);
            if ($linea !== null) {
                $out[] = $linea;
            }
        }
        if ($out === []) {
            throw new InvalidArgumentException('No hay movimientos en el extracto');
        }

        return $out;
    }

    public function reconoceFilas(array $filas): bool
    {
        return $this->buscarCabecera($filas) !== null;
    }

    /**
     * La firma de Arquia: «Tipo movimiento» y «Nro. Apunte» junto a fecha de operación e importe.
     *
     * @param list<list<string>> $filas
     * @return array{0:int, 1:array{fecha:int, concepto:int, importe:int, apunte:int}}|null
     */
    private function buscarCabecera(array $filas): ?array
    {
        foreach ($filas as $i => $cols) {
            if ($this->filaVacia($cols)) {
                continue;
            }
            $map = $this->mapaCabecera(array_map('strval', $cols));
            $fecha = $this->buscarColumna($map, ['fecha de la operacion', 'fecha operacion']);
            $concepto = $this->buscarColumna($map, ['tipo movimiento']);
            $importe = $this->buscarColumna($map, ['importe']);
            $apunte = $this->buscarColumna($map, ['nro apunte', 'n apunte', 'numero apunte']);
            if ($fecha !== null && $concepto !== null && $importe !== null && $apunte !== null) {
                return [$i, [
                    'fecha' => $fecha,
                    'concepto' => $concepto,
                    'importe' => $importe,
                    'apunte' => $apunte,
                ]];
            }
        }

        return null;
    }

    /**
     * @param list<string> $cols
     * @param array{fecha:int, concepto:int, importe:int, apunte:int} $idx
     */
    private function linea(array $cols, array $idx, int $n): ?LineaExtractoBanco
    {
        $fechaRaw = $this->celda($cols, $idx['fecha']);
        $concepto = $this->celda($cols, $idx['concepto']);
        $importeRaw = $this->celda($cols, $idx['importe']);
        if ($fechaRaw === '' && $importeRaw === '') {
            return null;
        }
        try {
            $fecha = $this->fechaArquia($fechaRaw);
            $dinero = $this->importeExtracto($importeRaw);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Fila ' . $n . ': ' . $e->getMessage());
        }
        if ($dinero->isZero()) {
            return null;
        }
        $apunte = $this->celda($cols, $idx['apunte']);
        $huella = hash('sha256', implode('|', [
            'arquia',
            $fecha,
            (string) $dinero->toCents(),
            mb_strtolower($concepto),
            $apunte,
        ]));

        return new LineaExtractoBanco(
            $fecha,
            $dinero->toCents(),
            $concepto !== '' ? $concepto : 'Movimiento Arquia',
            $huella,
        );
    }

    /** Número de serie Excel (con o sin hora) o fecha de texto habitual. */
    private function fechaArquia(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^\d{5}(\.\d+)?$/', $raw) === 1) {
            $dias = (int) floor((float) $raw);
            $base = new DateTimeImmutable('1899-12-30');

            return $base->modify('+' . $dias . ' days')->format('Y-m-d');
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]/', $raw, $m) === 1) {
            $raw = $m[1];
        }

        return $this->fechaExtracto($raw);
    }
}
