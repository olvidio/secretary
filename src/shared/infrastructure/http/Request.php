<?php

declare(strict_types=1);

namespace src\shared\infrastructure\http;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $session
     * @param array<string, string> $headers
     * @param array<string, mixed> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $session,
        public readonly string $rawBody = '',
        public readonly array $headers = [],
        public readonly array $files = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $raw = file_get_contents('php://input') ?: '';
        $body = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($raw !== '' && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $body,
            $_SESSION ?? [],
            $raw,
            self::cabecerasDesdeServidor(),
            $_FILES,
        );
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $info = $this->files[$key] ?? null;

        return is_array($info) ? $info : null;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $v = $this->query[$key] ?? $default;
        return $v === null ? null : (string) $v;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $v = $this->headers[strtolower($name)] ?? null;

        return $v === null ? null : (string) $v;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        return $this->body;
    }

    /** @return array<string, string> */
    private static function cabecerasDesdeServidor(): array
    {
        $out = [];
        foreach ($_SERVER as $k => $v) {
            if (!is_string($k) || !is_string($v)) {
                continue;
            }
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $out[$name] = $v;
            }
        }
        if (!empty($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])) {
            $out['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        return $out;
    }
}
