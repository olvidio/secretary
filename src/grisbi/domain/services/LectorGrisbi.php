<?php

declare(strict_types=1);

namespace src\grisbi\domain\services;

require_once __DIR__ . '/../LibroGrisbi.php';

use InvalidArgumentException;
use SimpleXMLElement;
use src\grisbi\domain\CategoriaGrisbi;
use src\grisbi\domain\CuentaGrisbi;
use src\grisbi\domain\LibroGrisbi;
use src\grisbi\domain\ListadoGrisbi;
use src\grisbi\domain\MovimientoGrisbi;
use src\grisbi\domain\SubcategoriaGrisbi;
use src\grisbi\domain\TerceroGrisbi;

final class LectorGrisbi
{
    public function leer(string $xml): LibroGrisbi
    {
        $prev = libxml_use_internal_errors(true);
        try {
            $raiz = simplexml_load_string($xml);
        } finally {
            libxml_use_internal_errors($prev);
        }
        if (!$raiz instanceof SimpleXMLElement || $raiz->getName() !== 'Grisbi') {
            throw new InvalidArgumentException('El XML no es un fichero Grisbi válido');
        }

        // El atributo Date_format es el de la pantalla. En el XML, Dt va en mes/día/año.
        $formatoFecha = 'm/d/Y';
        $cuentas = $this->leerCuentas($raiz);
        $categorias = $this->leerCategorias($raiz);
        $idsCategorias = array_map(static fn (CategoriaGrisbi $c): int => $c->numero, $categorias);
        $subcategorias = $this->leerSubcategorias($raiz);
        $terceros = $this->leerTerceros($raiz);
        $movimientos = $this->leerMovimientos($raiz, $formatoFecha);
        $listados = $this->leerListados($raiz, $formatoFecha, $idsCategorias);

        return new LibroGrisbi($cuentas, $categorias, $subcategorias, $terceros, $movimientos, $listados);
    }

    /** @return list<CuentaGrisbi> */
    private function leerCuentas(SimpleXMLElement $raiz): array
    {
        $out = [];
        foreach ($raiz->Account as $nodo) {
            $kind = (int) ($nodo['Kind'] ?? 0);
            $out[] = new CuentaGrisbi(
                (int) ($nodo['Number'] ?? 0),
                (string) ($nodo['Name'] ?? ''),
                $kind === 1 ? 'caja' : 'banco',
            );
        }

        return $out;
    }

    /** @return list<CategoriaGrisbi> */
    private function leerCategorias(SimpleXMLElement $raiz): array
    {
        $out = [];
        foreach ($raiz->Category as $nodo) {
            $nombre = $this->textoAtributo($nodo, 'Na');
            $kd = (int) ($nodo['Kd'] ?? 0);
            $naturaleza = $kd === 1 ? 'gasto' : 'ingreso';
            $naturaleza = $this->naturalezaPorNombre($nombre, $naturaleza);
            $out[] = new CategoriaGrisbi(
                (int) ($nodo['Nb'] ?? 0),
                $nombre,
                $naturaleza,
            );
        }

        return $out;
    }

    /** @return list<SubcategoriaGrisbi> */
    private function leerSubcategorias(SimpleXMLElement $raiz): array
    {
        $out = [];
        foreach ($raiz->Sub_category as $nodo) {
            $out[] = new SubcategoriaGrisbi(
                (int) ($nodo['Nbc'] ?? 0),
                (int) ($nodo['Nb'] ?? 0),
                $this->textoAtributo($nodo, 'Na'),
            );
        }

        return $out;
    }

    /** @return list<TerceroGrisbi> */
    private function leerTerceros(SimpleXMLElement $raiz): array
    {
        $out = [];
        foreach ($raiz->Party as $nodo) {
            $out[] = new TerceroGrisbi(
                (int) ($nodo['Nb'] ?? 0),
                $this->textoAtributo($nodo, 'Na'),
            );
        }

        return $out;
    }

    /** @return list<MovimientoGrisbi> */
    private function leerMovimientos(SimpleXMLElement $raiz, string $formatoFecha): array
    {
        $out = [];
        foreach ($raiz->Transaction as $nodo) {
            $trt = (int) ($nodo['Trt'] ?? 0);
            $out[] = new MovimientoGrisbi(
                (int) ($nodo['Ac'] ?? 0),
                (int) ($nodo['Nb'] ?? 0),
                $this->parsearFecha((string) ($nodo['Dt'] ?? ''), $formatoFecha),
                $this->aCentimos((string) ($nodo['Am'] ?? '0')),
                (int) ($nodo['Ca'] ?? 0),
                (int) ($nodo['Sca'] ?? 0),
                (int) ($nodo['Pa'] ?? 0),
                $this->textoAtributo($nodo, 'No'),
                $trt,
            );
        }

        return $out;
    }

    /**
     * @param list<int> $idsCategorias
     *
     * @return list<ListadoGrisbi>
     */
    private function leerListados(SimpleXMLElement $raiz, string $formatoFecha, array $idsCategorias): array
    {
        $catSet = array_fill_keys($idsCategorias, true);
        $out = [];
        foreach ($raiz->Report as $nodo) {
            $categRaw = (string) ($nodo['Categ_selected'] ?? '');
            $out[] = new ListadoGrisbi(
                (string) ($nodo['Name'] ?? ''),
                $this->parsearCategSelected($categRaw, $catSet),
                $this->parsearPayeeSelected((string) ($nodo['Payee_selected'] ?? '')),
                (string) ($nodo['Show_transaction'] ?? '') === '1',
                (string) ($nodo['Categ_show_amount'] ?? '') === '1',
                $this->fechaOpcional((string) ($nodo['Date_beginning'] ?? ''), $formatoFecha),
                $this->fechaOpcional((string) ($nodo['Date_end'] ?? ''), $formatoFecha),
            );
        }

        return $out;
    }

    private function naturalezaPorNombre(string $nombre, string $base): string
    {
        $norm = mb_strtolower($nombre, 'UTF-8');
        if (str_contains($norm, 'saldo inicial')) {
            return 'apertura';
        }
        if (str_contains($norm, 'transfer')) {
            return 'traspaso';
        }

        return $base;
    }

    private function textoAtributo(SimpleXMLElement $nodo, string $atributo): string
    {
        $v = (string) ($nodo[$atributo] ?? '');
        if ($v === '(null)') {
            return '';
        }

        return $v;
    }

    private function parsearFecha(string $dt, string $formatoPhp): string
    {
        $dt = trim($dt);
        if ($dt === '' || $dt === '(null)') {
            throw new InvalidArgumentException('Fecha de movimiento no válida');
        }
        $parsed = \DateTimeImmutable::createFromFormat('!' . $formatoPhp, $dt);
        $errores = \DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))) {
            throw new InvalidArgumentException('Fecha de movimiento no válida: ' . $dt);
        }

        return $parsed->format('Y-m-d');
    }

    private function fechaOpcional(string $dt, string $formatoPhp): ?string
    {
        $dt = trim($dt);
        if ($dt === '' || $dt === '(null)') {
            return null;
        }

        return $this->parsearFecha($dt, $formatoPhp);
    }

    private function aCentimos(string $am): int
    {
        $am = trim($am);
        if ($am === '') {
            return 0;
        }
        if (function_exists('bcmul')) {
            $cent = bcmul($am, '100', 10);
            if (bccomp($cent, '0', 10) >= 0) {
                return (int) bcadd($cent, '0.5', 0);
            }

            return (int) bcsub($cent, '0.5', 0);
        }

        return (int) round((float) $am * 100, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * @param array<int, true> $catSet
     *
     * @return list<array{categoria: int, subcategoria: ?int}>
     */
    private function parsearCategSelected(string $raw, array $catSet): array
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '(null)') {
            return [];
        }
        $tokens = array_map('intval', array_values(array_filter(explode('/', $raw), static fn (string $p): bool => $p !== '')));
        if ($tokens === []) {
            return [];
        }
        $out = [];
        $pos = 0;
        $n = count($tokens);
        while ($pos < $n) {
            $cat = $tokens[$pos];
            ++$pos;
            if ($pos >= $n) {
                $out[] = ['categoria' => $cat, 'subcategoria' => null];
                break;
            }
            $subsEmpezadas = false;
            while ($pos < $n) {
                $t = $tokens[$pos];
                if (isset($catSet[$t]) && $subsEmpezadas) {
                    break;
                }
                $subsEmpezadas = true;
                $out[] = ['categoria' => $cat, 'subcategoria' => $t === 0 ? null : $t];
                ++$pos;
            }
            if (!$subsEmpezadas) {
                $out[] = ['categoria' => $cat, 'subcategoria' => null];
            }
        }

        return $out;
    }

    /** @return list<int> */
    private function parsearPayeeSelected(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '(null)') {
            return [];
        }
        $partes = explode('/-/', $raw);
        $out = [];
        foreach ($partes as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $out[] = (int) $p;
        }

        return $out;
    }
}
