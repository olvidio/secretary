<?php

declare(strict_types=1);

namespace src\importacion\domain\services;

use InvalidArgumentException;
use src\importacion\domain\contracts\LectorExtractoEnFilas;
use src\importacion\domain\contracts\ReconocedorExtractoBanco;
use src\importacion\domain\value_objects\LineaExtractoBanco;

/**
 * Extracto de movimientos de Banco Santander (descarga Excel/CSV desde la app o web).
 *
 * El .xls habitual trae metadatos (CUENTA SANTANDER, IBAN, titular) y bloque «Movimientos»
 * sin fila de cabecera: fecha operación, fecha valor, concepto, importe y saldo en columnas fijas.
 */
final class LectorCsvSantander implements LectorExtractoEnFilas, ReconocedorExtractoBanco
{
    use TablaExtractoCsv;

    private const COL_FECHA = 0;
    private const COL_FECHA_VALOR = 1;
    private const COL_CONCEPTO = 2;
    private const COL_IMPORTE = 3;
    private const COL_SALDO = 4;

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
        if (!$this->reconoceFilas($filas)) {
            throw new InvalidArgumentException(
                'Este fichero no parece un extracto de Banco Santander. Compruebe el banco origen.'
            );
        }
        $filas = array_map(
            fn (array $cols) => array_map(fn ($c) => $this->aUtf8((string) $c), $cols),
            $filas,
        );
        $out = [];
        $n = count($filas);
        for ($i = 0; $i < $n; $i++) {
            $linea = $this->linea($filas[$i], $i + 1);
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
        if (!$this->tieneMarcaSantander($filas)) {
            return false;
        }

        return $this->primerIndiceMovimiento($filas) !== null;
    }

    /**
     * @param list<list<string>> $filas
     */
    private function tieneMarcaSantander(array $filas): bool
    {
        foreach ($filas as $cols) {
            foreach ($cols as $celda) {
                $n = $this->normalizarCabecera((string) $celda);
                if (str_contains($n, 'cuenta santander')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<list<string>> $filas
     */
    private function primerIndiceMovimiento(array $filas): ?int
    {
        foreach ($filas as $i => $cols) {
            if ($this->linea($cols, $i + 1) !== null) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<string> $cols
     */
    private function linea(array $cols, int $n): ?LineaExtractoBanco
    {
        if ($this->filaVacia($cols)) {
            return null;
        }
        $fechaRaw = $this->celda($cols, self::COL_FECHA);
        if (!$this->pareceFechaOperacion($fechaRaw)) {
            return null;
        }
        $concepto = $this->celda($cols, self::COL_CONCEPTO);
        $importeRaw = $this->celda($cols, self::COL_IMPORTE);
        if ($importeRaw === '') {
            return null;
        }
        $fechaValorRaw = $this->celda($cols, self::COL_FECHA_VALOR);
        $saldoRaw = $this->celda($cols, self::COL_SALDO);
        try {
            $fecha = $this->fechaExtracto($fechaRaw);
            $dinero = $this->importeExtracto($importeRaw);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Fila ' . $n . ': ' . $e->getMessage());
        }
        if ($dinero->isZero()) {
            return null;
        }
        $texto = $concepto !== '' ? $concepto : 'Movimiento Banco Santander';
        $huella = hash('sha256', implode('|', [
            'santander',
            $fecha,
            (string) $dinero->toCents(),
            mb_strtolower($concepto),
            mb_strtolower($fechaValorRaw),
            mb_strtolower($saldoRaw),
        ]));

        return new LineaExtractoBanco($fecha, $dinero->toCents(), $texto, $huella);
    }

    private function pareceFechaOperacion(string $raw): bool
    {
        $raw = trim($raw);
        if ($raw === '') {
            return false;
        }
        $n = $this->normalizarCabecera($raw);
        if (in_array($n, ['movimientos', 'titular', 'saldo', 'cuenta santander'], true)) {
            return false;
        }
        try {
            $this->fechaExtracto($raw);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
