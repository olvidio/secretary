<?php

declare(strict_types=1);

namespace src\remesas\domain\services;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use src\remesas\domain\entity\RemesaLinea;

/**
 * Documento XML de la remesa (urn:secretario:mensajes:1.0).
 * No es el sobre de transporte: ver docs/dev/mensajes/remesa.md.
 */
final class MensajeRemesa
{
    public const NS = 'urn:secretario:mensajes:1.0';

    public const ACCIONES = ['envio', 'sustitucion'];

    /**
     * @param list<array{codigo:string,cents:int}> $lineas
     */
    public function __construct(
        public readonly string $idMensaje,
        public readonly string $accion,
        public readonly DateTimeImmutable $emitido,
        public readonly string $emisorIniciales,
        public readonly string $receptorCodigo,
        public readonly int $anio,
        public readonly int $mes,
        public readonly int $version,
        public readonly ?string $nota,
        public readonly int $disponibleCents,
        public readonly array $lineas,
        public readonly string $hash,
    ) {
        $this->comprobarPartes();
        $this->comprobarHash();
    }

    /**
     * @param list<RemesaLinea> $lineas
     */
    public static function componer(
        string $receptorCodigo,
        string $emisorIniciales,
        int $anio,
        int $mes,
        int $version,
        ?string $nota,
        int $disponibleCents,
        array $lineas,
        string $hash,
        string $accion,
        ?string $idMensaje = null,
        ?DateTimeImmutable $emitido = null,
    ): self {
        $pares = [];
        foreach ($lineas as $linea) {
            $pares[] = ['codigo' => $linea->codigoMaestro, 'cents' => $linea->importeCents];
        }

        $mensaje = new self(
            $idMensaje ?? self::uuid(),
            $accion,
            $emitido ?? new DateTimeImmutable('now'),
            $emisorIniciales,
            $receptorCodigo,
            $anio,
            $mes,
            $version,
            self::notaVacia($nota),
            $disponibleCents,
            self::ordenar($pares),
            $hash,
        );
        $mensaje->xml();

        return $mensaje;
    }

    public static function leer(string $xml): self
    {
        $dom = self::dom($xml);
        self::validar($dom);
        $raiz = $dom->documentElement;
        if (!$raiz instanceof DOMElement) {
            throw new InvalidArgumentException('XML de remesa vacío');
        }
        $cabecera = self::hijo($raiz, 'cabecera');
        $periodo = self::hijo($raiz, 'periodo');
        $nota = self::hijoOpcional($raiz, 'nota');
        $disponible = self::hijo($raiz, 'disponible');
        $lineasNodo = self::hijo($raiz, 'lineas');
        $pares = [];
        foreach ($lineasNodo->childNodes as $nodo) {
            if (!$nodo instanceof DOMElement || $nodo->localName !== 'linea') {
                continue;
            }
            $pares[] = [
                'codigo' => $nodo->getAttribute('codigo'),
                'cents' => self::entero($nodo->getAttribute('cents'), 'cents'),
            ];
        }
        $emisor = trim(self::hijo($cabecera, 'emisor')->textContent);
        $receptor = trim(self::hijo($cabecera, 'receptor')->textContent);
        $anio = self::entero($periodo->getAttribute('anio'), 'anio');
        $mes = self::entero($periodo->getAttribute('mes'), 'mes');
        $conversacion = trim(self::hijo($cabecera, 'conversacion')->textContent);
        $esperada = self::conversacionDe($receptor, $emisor, $anio, $mes);
        if ($conversacion !== $esperada) {
            throw new InvalidArgumentException('La conversación no coincide con emisor, receptor y periodo');
        }
        $emitido = self::fecha(trim(self::hijo($cabecera, 'emitido')->textContent));

        return new self(
            trim(self::hijo($cabecera, 'idMensaje')->textContent),
            trim(self::hijo($cabecera, 'accion')->textContent),
            $emitido,
            $emisor,
            $receptor,
            $anio,
            $mes,
            self::entero(trim(self::hijo($raiz, 'version')->textContent), 'version'),
            $nota === null ? null : self::notaVacia($nota->textContent),
            self::entero($disponible->getAttribute('cents'), 'disponible'),
            self::ordenar($pares),
            trim(self::hijo($raiz, 'hash')->textContent),
        );
    }

    public function conversacion(): string
    {
        return self::conversacionDe($this->receptorCodigo, $this->emisorIniciales, $this->anio, $this->mes);
    }

    public function xml(): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $raiz = $dom->createElementNS(self::NS, 'remesa');
        $raiz->setAttribute('versionEsquema', '1');
        $dom->appendChild($raiz);

        $cabecera = $dom->createElementNS(self::NS, 'cabecera');
        $cabecera->appendChild($dom->createElementNS(self::NS, 'idMensaje', $this->idMensaje));
        $cabecera->appendChild($dom->createElementNS(self::NS, 'conversacion', $this->conversacion()));
        $cabecera->appendChild($dom->createElementNS(self::NS, 'accion', $this->accion));
        $cabecera->appendChild($dom->createElementNS(self::NS, 'emitido', $this->emitido->format(DateTimeImmutable::ATOM)));
        $emisor = $dom->createElementNS(self::NS, 'emisor', $this->emisorIniciales);
        $emisor->setAttribute('tipo', 'persona');
        $cabecera->appendChild($emisor);
        $receptor = $dom->createElementNS(self::NS, 'receptor', $this->receptorCodigo);
        $receptor->setAttribute('tipo', 'centro');
        $cabecera->appendChild($receptor);
        $raiz->appendChild($cabecera);

        $periodo = $dom->createElementNS(self::NS, 'periodo');
        $periodo->setAttribute('anio', (string) $this->anio);
        $periodo->setAttribute('mes', (string) $this->mes);
        $raiz->appendChild($periodo);
        $raiz->appendChild($dom->createElementNS(self::NS, 'version', (string) $this->version));
        if ($this->nota !== null) {
            $raiz->appendChild($dom->createElementNS(self::NS, 'nota', $this->nota));
        }
        $disponible = $dom->createElementNS(self::NS, 'disponible');
        $disponible->setAttribute('moneda', 'EUR');
        $disponible->setAttribute('cents', (string) $this->disponibleCents);
        $raiz->appendChild($disponible);

        $lineas = $dom->createElementNS(self::NS, 'lineas');
        foreach ($this->lineas as $linea) {
            $nodo = $dom->createElementNS(self::NS, 'linea');
            $nodo->setAttribute('codigo', $linea['codigo']);
            $nodo->setAttribute('cents', (string) $linea['cents']);
            $lineas->appendChild($nodo);
        }
        $raiz->appendChild($lineas);

        $hash = $dom->createElementNS(self::NS, 'hash', $this->hash);
        $hash->setAttribute('algoritmo', 'sha-256');
        $raiz->appendChild($hash);

        self::validar($dom);
        $xml = $dom->saveXML();
        if (!is_string($xml) || $xml === '') {
            throw new InvalidArgumentException('No se pudo escribir el XML de la remesa');
        }

        return $xml;
    }

    public static function conversacionDe(string $receptorCodigo, string $emisorIniciales, int $anio, int $mes): string
    {
        return $receptorCodigo . '/' . $emisorIniciales . '/' . sprintf('%04d-%02d', $anio, $mes);
    }

    private function comprobarPartes(): void
    {
        self::exigirParte($this->emisorIniciales, 'emisor');
        self::exigirParte($this->receptorCodigo, 'receptor');
        if ($this->anio < 1900 || $this->anio > 2200) {
            throw new InvalidArgumentException('El año de la remesa no es válido');
        }
        if ($this->mes < 1 || $this->mes > 12) {
            throw new InvalidArgumentException('El mes debe estar entre 1 y 12');
        }
        if ($this->version < 1) {
            throw new InvalidArgumentException('La versión debe ser al menos 1');
        }
        if (!in_array($this->accion, self::ACCIONES, true)) {
            throw new InvalidArgumentException('Acción de remesa no válida: ' . $this->accion);
        }
        if ($this->disponibleCents < 0) {
            throw new InvalidArgumentException('El disponible no puede ser negativo');
        }
        if (!preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $this->idMensaje)) {
            throw new InvalidArgumentException('idMensaje no es un UUID');
        }
        $vistos = [];
        foreach ($this->lineas as $linea) {
            if (!preg_match('/^[0-9A-Za-z]{1,12}$/', $linea['codigo'])) {
                throw new InvalidArgumentException('Código de línea no válido: ' . $linea['codigo']);
            }
            if (isset($vistos[$linea['codigo']])) {
                throw new InvalidArgumentException('Código de línea repetido: ' . $linea['codigo']);
            }
            $vistos[$linea['codigo']] = true;
        }
    }

    private function comprobarHash(): void
    {
        $objs = [];
        foreach ($this->lineas as $linea) {
            $objs[] = new RemesaLinea(null, null, $linea['codigo'], $linea['cents'], []);
        }
        $esperado = HashRemesa::deLineas($objs, $this->disponibleCents);
        if (!hash_equals($esperado, $this->hash)) {
            throw new InvalidArgumentException('El hash del mensaje no coincide con las líneas y el disponible');
        }
    }

    private static function exigirParte(string $valor, string $campo): void
    {
        if ($valor === '' || preg_match('/[\s\/]/', $valor) === 1 || strlen($valor) > 64) {
            throw new InvalidArgumentException('Identificador de ' . $campo . ' no válido');
        }
    }

    /**
     * @param list<array{codigo:string,cents:int}> $pares
     * @return list<array{codigo:string,cents:int}>
     */
    private static function ordenar(array $pares): array
    {
        usort($pares, static fn (array $a, array $b): int => $a['codigo'] <=> $b['codigo']);

        return array_values($pares);
    }

    private static function notaVacia(?string $nota): ?string
    {
        if ($nota === null) {
            return null;
        }
        $nota = trim($nota);

        return $nota === '' ? null : $nota;
    }

    private static function fecha(string $valor): DateTimeImmutable
    {
        $emitido = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $valor);
        if ($emitido instanceof DateTimeImmutable) {
            return $emitido;
        }
        try {
            return new DateTimeImmutable($valor);
        } catch (\Exception) {
            throw new InvalidArgumentException('Fecha de emisión no válida');
        }
    }

    private static function entero(string $valor, string $campo): int
    {
        if (!preg_match('/^-?\d+$/', $valor)) {
            throw new InvalidArgumentException($campo . ' no es un entero');
        }

        return (int) $valor;
    }

    private static function dom(string $xml): DOMDocument
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new DOMDocument();
        $ok = $dom->loadXML($xml, LIBXML_NONET);
        $errores = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            $msg = 'XML de remesa ilegible';
            foreach ($errores as $e) {
                $msg .= '; ' . trim($e->message);
            }
            throw new InvalidArgumentException($msg);
        }

        return $dom;
    }

    private static function validar(DOMDocument $dom): void
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $ok = $dom->schemaValidate(self::xsd());
        $errores = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($ok) {
            return;
        }
        $msg = 'XML de remesa no válido';
        foreach ($errores as $e) {
            $msg .= '; ' . trim($e->message);
        }
        throw new InvalidArgumentException($msg);
    }

    private static function xsd(): string
    {
        return dirname(__DIR__, 4) . '/docs/dev/mensajes/remesa-1.0.xsd';
    }

    private static function hijo(DOMElement $padre, string $nombre): DOMElement
    {
        $nodo = self::hijoOpcional($padre, $nombre);
        if ($nodo === null) {
            throw new InvalidArgumentException('Falta el elemento ' . $nombre);
        }

        return $nodo;
    }

    private static function hijoOpcional(DOMElement $padre, string $nombre): ?DOMElement
    {
        foreach ($padre->childNodes as $nodo) {
            if ($nodo instanceof DOMElement && $nodo->localName === $nombre && $nodo->namespaceURI === self::NS) {
                return $nodo;
            }
        }

        return null;
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);

        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-'
            . substr($h, 16, 4) . '-' . substr($h, 20, 12);
    }
}
