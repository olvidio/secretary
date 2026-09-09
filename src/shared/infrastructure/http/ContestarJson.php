<?php

declare(strict_types=1);

namespace src\shared\infrastructure\http;

final class ContestarJson
{
    /** @param array<string, mixed> $data */
    public static function ok(array $data = []): Response
    {
        return Response::json(['ok' => true] + $data);
    }

    public static function error(string $mensaje, int $status = 400): Response
    {
        return Response::json(['ok' => false, 'error' => $mensaje], $status);
    }
}
