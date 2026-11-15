<?php

declare(strict_types=1);

namespace src\importacion\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\importacion\domain\contracts\LectorExtractoEnFilas;
use src\importacion\domain\contracts\ReconocedorExtractoBanco;
use src\importacion\domain\value_objects\LineaExtractoBanco;

/**
 * Formato propio de Secretario, para bancos sin lector: un CSV o Excel que
 * prepara el usuario con tres columnas, «Fecha», «Concepto» e «Importe».
 *
 *   Fecha;Concepto;Importe
 *   15/01/2026;Compra de papelería;-23,50
 *   16/01/2026;Donativo;100,00
 *
 * El importe va en positivo si entra dinero y en negativo si sale. La cabecera
 * es obligatoria y debe ser la primera fila con contenido; admite los nombres
 * en catalán y algún sinónimo, en cualquier orden.
 *
 * Solo se reconoce si la cabecera lleva esas tres columnas y ninguna más: los
 * extractos de banco traen saldo, fecha valor, etc., y así ninguno se toma por
 * este formato (el lector de CaixaBank también admite Fecha/Concepto/Importe
 * entre otras columnas, por eso en la detección este va antes).
 */
final class LectorCsvPropio implements LectorExtractoEnFilas, ReconocedorExtractoBanco
{
    use TablaExtractoCsv;

    public const ID = 'secretario';

    public function leer(string $contenido): array
    {
        $contenido = $this->aUtf8($this->sinBom($contenido));
        if (trim($contenido) === '') {
            throw new InvalidArgumentException('El fichero CSV está vacío');
        }
        $filas = $this->filasCsv($contenido, $this->delimitadorCabecera($contenido));
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
                'El fichero no tiene la cabecera Fecha, Concepto e Importe en la primera fila.'
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
     * La cabecera es la primera fila con contenido; si no lleva las tres columnas, no es este formato.
     *
     * @param list<list<string>> $filas
     * @return array{0:int, 1:array{fecha:int, concepto:int, importe:int}}|null
     */
    private function buscarCabecera(array $filas): ?array
    {
        foreach ($filas as $i => $cols) {
            if ($this->filaVacia($cols)) {
                continue;
            }
            $map = $this->mapaCabecera(array_map('strval', $cols));
            if (count($map) !== 3) {
                return null;
            }
            $fecha = $this->buscarColumna($map, ['fecha', 'data']);
            $concepto = $this->buscarColumna($map, ['concepto', 'concepte', 'descripcion', 'descripcio', 'observaciones', 'observacions']);
            $importe = $this->buscarColumna($map, ['importe', 'import', 'cantidad', 'quantitat']);
            if ($fecha === null || $concepto === null || $importe === null) {
                return null;
            }

            return [$i, ['fecha' => $fecha, 'concepto' => $concepto, 'importe' => $importe]];
        }

        return null;
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
        if ($fechaRaw === '' && $importeRaw === '') {
            return null;
        }
        try {
            $fecha = $this->fecha($fechaRaw);
            $dinero = $this->importeExtracto($importeRaw);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Fila ' . $n . ': ' . $e->getMessage());
        }
        if ($dinero->isZero()) {
            return null;
        }
        $huella = hash('sha256', implode('|', [
            self::ID,
            $fecha,
            (string) $dinero->toCents(),
            mb_strtolower($concepto),
        ]));

        return new LineaExtractoBanco(
            $fecha,
            $dinero->toCents(),
            $concepto !== '' ? $concepto : 'Movimiento',
            $huella,
        );
    }

    /** Fecha de texto (15/01/2026, 2026-01-15…) o número de serie de una celda de fecha de Excel. */
    private function fecha(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^\d{5}(\.\d+)?$/', $raw) === 1) {
            return (new DateTimeImmutable('1899-12-30'))
                ->modify('+' . (int) floor((float) $raw) . ' days')
                ->format('Y-m-d');
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]/', $raw, $m) === 1) {
            $raw = $m[1];
        }

        return $this->fechaExtracto($raw);
    }

    /**
     * El separador se decide por la cabecera: en el resto del fichero las comas
     * de los importes (23,50) falsearían la cuenta.
     */
    private function delimitadorCabecera(string $contenido): string
    {
        $primera = strtok(ltrim($contenido), "\r\n") ?: $contenido;
        if (substr_count($primera, "\t") > 0) {
            return "\t";
        }

        return substr_count($primera, ';') >= substr_count($primera, ',') && str_contains($primera, ';') ? ';' : ',';
    }
}
