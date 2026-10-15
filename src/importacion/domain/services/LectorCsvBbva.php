<?php

declare(strict_types=1);

namespace src\importacion\domain\services;

use InvalidArgumentException;
use src\importacion\domain\contracts\LectorExtractoEnFilas;
use src\importacion\domain\contracts\ReconocedorExtractoBanco;
use src\importacion\domain\value_objects\LineaExtractoBanco;

/**
 * Extracto de movimientos de BBVA (banca online: descargar Excel o CSV).
 *
 * Exportaciones CSV/Excel de movimientos (web o histórico). Cabeceras en castellano, catalán u otro
 * idioma de la app del banco: se reconocen por nombre de columna normalizado, no por idioma fijo.
 * La fecha que se asienta es la de operación, no la de valor.
 * Decimales con punto o con coma. Puede haber filas de titular antes de la cabecera.
 */
final class LectorCsvBbva implements LectorExtractoEnFilas, ReconocedorExtractoBanco
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
                'Este fichero no parece un extracto BBVA. Compruebe el banco origen.'
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

    public function reconoceFilas(array $filas): bool
    {
        foreach ($filas as $cols) {
            if ($this->filaVacia($cols)) {
                continue;
            }
            if ($this->indices($cols) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $cabecera
     * @return array{fecha:int, concepto:int, movimiento:int, observaciones:int, importe:int, codi:int, remesa:int}|null
     */
    private function indices(array $cabecera): ?array
    {
        $map = $this->mapaCabecera($cabecera);
        $fecha = $this->buscarColumna($map, [
            'fecha', 'fecha operacion', 'fecha oper', 'fecha de operacion', 'f operacion',
            'data proces', 'data process', 'fecha proceso', 'fecha proces',
        ]);
        if ($fecha === null) {
            $fecha = $this->buscarColumna($map, ['f valor', 'fecha valor', 'data valor']);
        }
        $importe = $this->buscarColumna($map, ['importe', 'importe eur', 'importe euros', 'import']);
        $concepto = $this->buscarColumna($map, ['concepto', 'concepte']);
        $movimiento = $this->buscarColumna($map, ['movimiento']);
        $formatoHistorico = $this->buscarColumna($map, ['codi', 'codigo']) !== null
            && $this->buscarColumna($map, ['remesa']) !== null;
        $formatoWeb = $this->buscarColumna($map, ['f valor', 'disponible']) !== null
            || ($concepto !== null && $movimiento !== null);
        $firma = $formatoHistorico || $formatoWeb;
        if ($fecha === null || $importe === null || !$firma) {
            return null;
        }

        return [
            'fecha' => $fecha,
            'concepto' => $concepto ?? -1,
            'movimiento' => $movimiento ?? -1,
            'observaciones' => $this->buscarColumna($map, ['observaciones', 'observacions']) ?? -1,
            'importe' => $importe,
            'codi' => $this->buscarColumna($map, ['codi', 'codigo']) ?? -1,
            'remesa' => $this->buscarColumna($map, ['remesa']) ?? -1,
        ];
    }

    /**
     * @param list<string> $cols
     * @param array{fecha:int, concepto:int, movimiento:int, observaciones:int, importe:int, codi:int, remesa:int} $idx
     */
    private function linea(array $cols, array $idx, int $n): ?LineaExtractoBanco
    {
        $fechaRaw = $this->celda($cols, $idx['fecha']);
        $concepto = $idx['concepto'] >= 0 ? $this->celda($cols, $idx['concepto']) : '';
        $mov = $idx['movimiento'] >= 0 ? $this->celda($cols, $idx['movimiento']) : '';
        $obs = $idx['observaciones'] >= 0 ? $this->celda($cols, $idx['observaciones']) : '';
        $codi = $idx['codi'] >= 0 ? $this->celda($cols, $idx['codi']) : '';
        $remesa = $idx['remesa'] >= 0 ? $this->celda($cols, $idx['remesa']) : '';
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
        $partes = [];
        foreach ([$concepto, $mov, $obs] as $parte) {
            if ($parte !== '' && !in_array($parte, $partes, true)) {
                $partes[] = $parte;
            }
        }
        $texto = $partes !== [] ? implode(' · ', $partes) : 'Movimiento BBVA';
        $huella = hash('sha256', implode('|', [
            'bbva',
            $fecha,
            (string) $dinero->toCents(),
            mb_strtolower($concepto),
            mb_strtolower($mov),
            mb_strtolower($obs),
            mb_strtolower($codi),
            mb_strtolower($remesa),
        ]));

        return new LineaExtractoBanco($fecha, $dinero->toCents(), $texto, $huella);
    }

    private function esRotulo(string $raw): bool
    {
        $n = $this->normalizarCabecera($raw);

        return in_array($n, ['fecha', 'f valor', 'importe', 'saldo anterior', 'saldo final', 'total'], true);
    }
}
