<?php

declare(strict_types=1);

namespace src\importacion\infrastructure\excel;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class XlsxReader
{
    /** @var array<int, string> */
    private array $strings = [];

    /** @var array<string, string> */
    private array $sheets = [];

    public function __construct(private readonly string $path)
    {
        if (!is_readable($path)) {
            throw new RuntimeException('No se puede leer ' . $path);
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('No es un libro Excel válido');
        }
        $this->parseWorkbook($zip);
        $this->parseStrings($zip);
        $zip->close();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sheet(string $name): array
    {
        if (!isset($this->sheets[$name])) {
            throw new RuntimeException('No existe la hoja ' . $name);
        }
        $zip = new ZipArchive();
        $zip->open($this->path);
        $xml = $zip->getFromName('xl/' . ltrim($this->sheets[$name], '/'));
        $zip->close();
        if (!is_string($xml)) {
            return [];
        }
        $sx = $this->xml($xml);
        $grid = [];
        foreach ($sx->xpath('//c') ?: [] as $c) {
            $ref = (string) $c['r'];
            if (!preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
                continue;
            }
            $val = $this->cellValue($c);
            if ($val === null) {
                continue;
            }
            $grid[(int) $m[2]][$m[1]] = $val;
        }
        ksort($grid);

        return $grid;
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_keys($this->sheets);
    }

    private function parseWorkbook(ZipArchive $zip): void
    {
        $xml = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!is_string($xml) || !is_string($rels)) {
            throw new RuntimeException('Libro Excel incompleto');
        }
        $relMap = [];
        $rx = $this->xml($rels);
        foreach ($rx->Relationship as $rel) {
            $relMap[(string) $rel['Id']] = (string) $rel['Target'];
        }
        $wx = $this->xml($xml);
        foreach ($wx->sheets->sheet as $sh) {
            $name = (string) $sh['name'];
            $rid = (string) $sh['id'];
            if ($name !== '' && isset($relMap[$rid])) {
                $this->sheets[$name] = $relMap[$rid];
            }
        }
    }

    private function parseStrings(ZipArchive $zip): void
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (!is_string($xml)) {
            return;
        }
        $sx = $this->xml($xml);
        foreach ($sx->si as $si) {
            $texts = [];
            foreach ($si->xpath('.//t') ?: [] as $t) {
                $texts[] = (string) $t;
            }
            $this->strings[] = implode('', $texts);
        }
    }

    private function cellValue(SimpleXMLElement $c): mixed
    {
        $type = (string) $c['t'];
        if ($type === 's') {
            $raw = (string) $c->v;
            $idx = (int) $raw;

            return $this->strings[$idx] ?? null;
        }
        if ($type === 'inlineStr') {
            $t = $c->xpath('.//t');

            return isset($t[0]) ? (string) $t[0] : null;
        }
        if (!isset($c->v)) {
            return null;
        }
        $raw = (string) $c->v;
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        return $raw;
    }

    private function xml(string $xml): SimpleXMLElement
    {
        $xml = preg_replace('/xmlns(:\w+)?="[^"]*"/', '', $xml) ?? $xml;
        $xml = preg_replace('/(<\/?)[\w.]+:(\w+)/', '$1$2', $xml) ?? $xml;
        $xml = preg_replace('/\s[\w.]+:(\w+=")/', ' $1', $xml) ?? $xml;
        $prev = libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);
        if ($sx === false) {
            throw new RuntimeException('XML de Excel ilegible');
        }

        return $sx;
    }
}
