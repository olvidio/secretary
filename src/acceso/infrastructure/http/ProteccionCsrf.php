<?php

declare(strict_types=1);

namespace src\acceso\infrastructure\http;

use src\shared\infrastructure\http\Request;

final class ProteccionCsrf
{
    public static function asegurarToken(): string
    {
        if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return (string) ($_SESSION['csrf'] ?? '');
    }

    public static function valido(Request $request): bool
    {
        $esperado = (string) ($request->session['csrf'] ?? '');
        if ($esperado === '') {
            return false;
        }
        $recibido = trim((string) $request->input('_csrf', ''));
        if ($recibido === '') {
            $recibido = trim((string) ($request->header('x-csrf-token') ?? ''));
        }
        if ($recibido === '') {
            return false;
        }

        return hash_equals($esperado, $recibido);
    }
}
