<?php

declare(strict_types=1);

namespace src\personal\domain\services;

use DateTimeImmutable;
use InvalidArgumentException;
use src\personal\domain\contracts\LectorCsvBanco;
use src\personal\domain\value_objects\LineaExtractoBanco;
use src\shared\domain\value_objects\Dinero;

/**
 * Extracto CSV de N26 (web: Descargas → actividad de la cuenta).
 * Admite el formato clásico (Date / Payee) y el actual (Booking Date / Partner Name).
 * Cabeceras en inglés, alemán, español o francés.
 */
final class LectorCsvN26 implements LectorCsvBanco
{
    public function leer(string $contenido): array
    {
        $contenido = $this->sinBom($contenido);
        if (trim($contenido) === '') {
            throw new InvalidArgumentException('El fichero CSV está vacío');
        }
        $delim = $this->delimitador($contenido);
        $filas = $this->filas($contenido, $delim);
        if ($filas === []) {
            throw new InvalidArgumentException('El fichero CSV está vacío');
        }
        $cabecera = array_shift($filas);
        $idx = $this->indices($cabecera);
        $out = [];
        foreach ($filas as $n => $cols) {
            if ($this->filaVacia($cols)) {
                continue;
            }
            $linea = $this->linea($cols, $idx, $n + 2);
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
     * @return array{fecha:int, payee:int, cuenta:int, tipo:int, ref:int, importe:int, categoria:?int}
     */
    private function indices(array $cabecera): array
    {
        $map = [];
        foreach ($cabecera as $i => $nombre) {
            $map[$this->normalizar($nombre)] = $i;
        }
        $fecha = $this->buscar($map, [
            'booking date', 'buchungsdatum', 'fecha de reserva', 'fecha contable',
            'date', 'datum', 'fecha',
            'value date', 'valutadatum', 'wertstellung', 'fecha valor',
        ]);
        if ($fecha === null) {
            $fecha = $this->buscarContiene($map, ['booking date', 'buchungsdatum'], []);
        }
        if ($fecha === null) {
            $fecha = $this->buscarContiene($map, ['date', 'datum', 'fecha'], []);
        }
        $payee = $this->buscar($map, [
            'partner name', 'partnername', 'nombre del socio',
            'payee', 'empfanger', 'beneficiario', 'destinatario', 'beneficiaire',
        ]);
        if ($payee === null) {
            $payee = $this->buscarContiene($map, ['partner name', 'payee', 'empfanger', 'beneficiario'], []);
        }
        $importe = $this->buscar($map, [
            'amount (eur)', 'amount eur', 'betrag (eur)', 'betrag eur',
            'importe (eur)', 'importe eur', 'montant (eur)', 'montant eur', 'montant',
        ]);
        if ($importe === null) {
            $importe = $this->buscarContiene($map, ['importe', 'betrag', 'amount', 'montant'], ['eur']);
        }
        if ($fecha === null || $importe === null) {
            throw new InvalidArgumentException(
                'Este fichero no parece un extracto N26. Compruebe el banco origen.'
            );
        }

        return [
            'fecha' => $fecha,
            'payee' => $payee ?? -1,
            'cuenta' => $this->buscar($map, [
                'partner iban', 'partneriban', 'iban',
                'account number', 'kontonummer', 'numero de cuenta', 'numero de compte',
            ]) ?? -1,
            'tipo' => $this->buscar($map, [
                'transaction type', 'transaktionstyp', 'tipo de transaccion', 'type de transaction', 'type', 'typ', 'tipo',
            ]) ?? -1,
            'ref' => $this->buscar($map, [
                'payment reference', 'verwendungszweck', 'referencia de pago', 'referencia', 'reference',
            ]) ?? -1,
            'importe' => $importe,
            'categoria' => $this->buscar($map, ['category', 'kategorie', 'categoria', 'categorie']),
        ];
    }

    /**
     * @param list<string> $cols
     * @param array{fecha:int, payee:int, cuenta:int, tipo:int, ref:int, importe:int, categoria:?int} $idx
     */
    private function linea(array $cols, array $idx, int $linea): ?LineaExtractoBanco
    {
        $fechaRaw = $this->celda($cols, $idx['fecha']);
        $payee = $this->celda($cols, $idx['payee']);
        $importeRaw = $this->celda($cols, $idx['importe']);
        if ($fechaRaw === '' && $payee === '' && $importeRaw === '') {
            return null;
        }
        try {
            $fecha = $this->fecha($fechaRaw);
            $dinero = Dinero::fromInput($importeRaw);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('Fila ' . $linea . ': ' . $e->getMessage());
        }
        if ($dinero->isZero()) {
            return null;
        }
        $ref = $idx['ref'] >= 0 ? $this->celda($cols, $idx['ref']) : '';
        $cuenta = $idx['cuenta'] >= 0 ? $this->celda($cols, $idx['cuenta']) : '';
        $tipo = $idx['tipo'] >= 0 ? $this->celda($cols, $idx['tipo']) : '';
        $cat = $idx['categoria'] !== null ? $this->celda($cols, $idx['categoria']) : '';
        $partes = [];
        if ($payee !== '') {
            $partes[] = $payee;
        }
        if ($ref !== '' && $ref !== $payee) {
            $partes[] = $ref;
        }
        if ($partes === [] && $tipo !== '') {
            $partes[] = $tipo;
        }
        $concepto = $partes !== [] ? implode(' · ', $partes) : 'Movimiento N26';
        $huella = hash('sha256', implode('|', [
            'n26',
            $fecha,
            (string) $dinero->toCents(),
            mb_strtolower($payee),
            mb_strtolower($ref),
            mb_strtolower($cuenta),
            mb_strtolower($tipo),
        ]));

        return new LineaExtractoBanco($fecha, $dinero->toCents(), $concepto, $huella, $cat);
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

    /** @param array<string, int> $map */
    private function buscarContiene(array $map, array $alguno, array $todos): ?int
    {
        foreach ($map as $nombre => $i) {
            $okAlguno = false;
            foreach ($alguno as $a) {
                if (str_contains($nombre, $a)) {
                    $okAlguno = true;
                    break;
                }
            }
            if (!$okAlguno) {
                continue;
            }
            $okTodos = true;
            foreach ($todos as $t) {
                if (!str_contains($nombre, $t)) {
                    $okTodos = false;
                    break;
                }
            }
            if ($okTodos) {
                return $i;
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
            'Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U', 'ß' => 'ss',
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'Â' => 'A', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Û' => 'U', 'Ç' => 'C',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ç' => 'c',
        ]);
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if (is_string($trans) && $trans !== '') {
            $s = $trans;
        }
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
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
        $primera = strtok($contenido, "\r\n") ?: $contenido;
        $comas = substr_count($primera, ',');
        $puntos = substr_count($primera, ';');
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
