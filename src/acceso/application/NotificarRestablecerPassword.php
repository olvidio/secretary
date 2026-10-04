<?php

declare(strict_types=1);

namespace src\acceso\application;

use src\shared\domain\contracts\EnviadorCorreo;
use src\shared\infrastructure\persistence\ConnectionFactory;

final class NotificarRestablecerPassword
{
    public function __construct(private readonly EnviadorCorreo $correo)
    {
    }

    /**
     * @param list<array{etiqueta: string, token: string}> $cuentas
     */
    public function ejecutar(string $email, array $cuentas): void
    {
        if ($cuentas === []) {
            return;
        }
        $base = rtrim(ConnectionFactory::env('APP_URL', 'http://127.0.0.1:8088') ?: 'http://127.0.0.1:8088', '/');
        $lineas = [];
        foreach ($cuentas as $cuenta) {
            $enlace = $base . '/restablecer-contrasena?token=' . rawurlencode($cuenta['token']);
            $lineas[] = $cuenta['etiqueta'] . "\n" . $enlace;
        }
        $asunto = _('Nueva contraseña en Secretario');
        $cuerpo = sprintf(
            _("Hola,\n\nHa pedido una contraseña nueva en Secretario. Cada enlace vale para una cuenta, caduca en 2 horas y solo puede usarse una vez:\n\n%s\n\nSi no ha sido usted, ignore este mensaje. La contraseña no cambia.\n"),
            implode("\n\n", $lineas),
        );
        $this->correo->enviar($email, $asunto, $cuerpo);
    }
}
