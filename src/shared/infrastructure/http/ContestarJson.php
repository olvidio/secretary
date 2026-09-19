<?php

declare(strict_types=1);

namespace src\shared\infrastructure\http;

use PDOException;

final class ContestarJson
{
    /** @param array<string, mixed> $data */
    public static function ok(array $data = []): Response
    {
        return Response::json(['ok' => true] + $data);
    }

    /** @param array<string, mixed> $extra */
    public static function error(string $mensaje, int $status = 400, array $extra = []): Response
    {
        return Response::json(['ok' => false, 'error' => $mensaje] + $extra, $status);
    }

    public static function errorPdo(PDOException $e): Response
    {
        return self::error(self::mensajePdo($e), 500);
    }

    public static function mensajePdo(PDOException $e): string
    {
        $msg = trim($e->getMessage());
        if (preg_match('/ERROR:\s*(.+)$/m', $msg, $m) === 1) {
            return trim($m[1]);
        }

        return $msg !== '' ? $msg : _("Error de base de datos");
    }
}
