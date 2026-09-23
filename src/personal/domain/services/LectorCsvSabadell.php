<?php

declare(strict_types=1);

namespace src\personal\domain\services;

use InvalidArgumentException;
use src\personal\domain\contracts\LectorExtractoEnFilas;
use src\personal\domain\value_objects\LineaExtractoBanco;

/**
 * Extracto de movimientos de Banco Sabadell (Sabadell Online: Descargar → Excel o CSV).
 *
 * Cabecera documentada en exportaciones reales:
 * FECHA OPER; FECHA VALOR; CONCEPTO; IMPORTE; DIVISA; SALDO.
 * También la variante corta Fecha; Concepto; Importe; Saldo.
 * La fecha que se asienta es la de operación. Decimales con coma y miles con punto.
 */
final class LectorCsvSabadell implements LectorExtractoEnFilas
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
                'Este fichero no parece un extracto de Banco Sabadell. Compruebe el banco origen.'
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
     * @return array{fecha:int, concepto:int, importe:int}|null
     */
    private function indices(array $cabecera): ?array
    {
        $map = $this->mapaCabecera($cabecera);
        $fechaOper = $this->buscarColumna($map, [
            'fecha oper', 'fecha operacion', 'fecha de operacion', 'f operacion', 'fecha de la operacion',
        ]);
        $fecha = $fechaOper ?? $this->buscarColumna($map, ['fecha', 'data']);
        if ($fecha === null) {
            $fecha = $this->buscarColumna($map, ['fecha valor', 'f valor']);
        }
        $concepto = $this->buscarColumna($map, ['concepto', 'descripcion']);
        $importe = $this->buscarColumna($map, ['importe', 'importe eur', 'importe euros']);
        $fechaValor = $this->buscarColumna($map, ['fecha valor', 'f valor']);
        $saldo = $this->buscarColumna($map, ['saldo']);
        $movimiento = $this->buscarColumna($map, ['movimiento']);
        $firma = $fechaOper !== null
            || ($fechaValor !== null && $movimiento === null)
            || ($saldo !== null && $movimiento === null);
        if ($fecha === null || $concepto === null || $importe === null || !$firma) {
            return null;
        }

        return [
            'fecha' => $fecha,
            'concepto' => $concepto,
            'importe' => $importe,
        ];
    }

    /**
     * @param list<string> $cols
     * @param array{fecha:int, concepto:int, importe:int} $idx
     */
    private function linea(array $cols, array $idx, int $n): ?LineaExtractoBanco
    {
        $fechaRaw = $this->celda($cols, $idx['fecha']);
        $concepto = $this->celda($cols, $idx['concepto']);
        $importeRaw = $this->celda($cols, $idx['importe']);
        if ($fechaRaw === '' && $concepto === '' && $importeRaw === '') {
            return null;
        }
        if ($this->esRotulo($fechaRaw) || $this->esRotulo($importeRaw)) {
            return null;
        }
        try {
            $fecha = $this->fechaExtracto($fechaRaw);
            $dinero = $this->importeExtracto($importeRaw);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Fila ' . $n . ': ' . $e->getMessage());
        }
        if ($dinero->isZero()) {
            return null;
        }
        $texto = $concepto !== '' ? $concepto : 'Movimiento Banco Sabadell';
        $huella = hash('sha256', implode('|', [
            'sabadell',
            $fecha,
            (string) $dinero->toCents(),
            mb_strtolower($concepto),
        ]));

        return new LineaExtractoBanco($fecha, $dinero->toCents(), $texto, $huella);
    }

    private function esRotulo(string $raw): bool
    {
        $n = $this->normalizarCabecera($raw);

        return in_array($n, [
            'fecha', 'fecha oper', 'fecha valor', 'importe', 'saldo anterior', 'saldo final', 'total',
        ], true);
    }
}
