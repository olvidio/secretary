<?php

declare(strict_types=1);

namespace src\shared\infrastructure\mail;

use RuntimeException;
use src\shared\domain\contracts\EnviadorCorreo;

/** Cliente SMTP mínimo (sin autenticación) para relay interno. */
final class SmtpEnviadorCorreo implements EnviadorCorreo
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $from,
    ) {
    }

    public function enviar(string $destinatario, string $asunto, string $cuerpoTexto): void
    {
        $destinatario = trim($destinatario);
        if ($destinatario === '') {
            throw new RuntimeException(_('Destinatario de correo vacío'));
        }
        $socket = @stream_socket_client(
            sprintf('tcp://%s:%d', $this->host, $this->port),
            $errno,
            $errstr,
            15,
        );
        if ($socket === false) {
            throw new RuntimeException(
                sprintf(_('No se pudo conectar al servidor de correo (%s:%d): %s'), $this->host, $this->port, $errstr)
            );
        }
        try {
            $this->leer($socket, [220]);
            $this->escribir($socket, 'EHLO secretario.local');
            $this->leer($socket, [250]);
            $this->escribir($socket, 'MAIL FROM:<' . $this->from . '>');
            $this->leer($socket, [250]);
            $this->escribir($socket, 'RCPT TO:<' . $destinatario . '>');
            $this->leer($socket, [250, 251]);
            $this->escribir($socket, 'DATA');
            $this->leer($socket, [354]);
            $mensaje = $this->construirMensaje($destinatario, $asunto, $cuerpoTexto);
            $this->escribir($socket, $mensaje . "\r\n.");
            $this->leer($socket, [250]);
            $this->escribir($socket, 'QUIT');
            $this->leer($socket, [221]);
        } finally {
            fclose($socket);
        }
    }

    private function construirMensaje(string $destinatario, string $asunto, string $cuerpoTexto): string
    {
        $asuntoCod = $this->codificarCabecera($asunto);
        $cuerpo = str_replace(["\r\n", "\r"], "\n", $cuerpoTexto);
        $cuerpo = str_replace("\n.", "\n..", $cuerpo);
        $cuerpo = str_replace("\n", "\r\n", $cuerpo);

        return implode("\r\n", [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . $this->from,
            'To: ' . $destinatario,
            'Subject: ' . $asuntoCod,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $cuerpo,
        ]);
    }

    private function codificarCabecera(string $texto): string
    {
        if (preg_match('/[^\x20-\x7E]/', $texto) === 1) {
            return '=?UTF-8?B?' . base64_encode($texto) . '?=';
        }

        return $texto;
    }

    /** @param resource $socket */
    private function escribir($socket, string $linea): void
    {
        if (fwrite($socket, $linea . "\r\n") === false) {
            throw new RuntimeException(_('Error al escribir en el servidor de correo'));
        }
    }

    /**
     * @param resource $socket
     * @param list<int> $codigosOk
     */
    private function leer($socket, array $codigosOk): void
    {
        $linea = fgets($socket);
        if ($linea === false) {
            throw new RuntimeException(_('El servidor de correo cerró la conexión'));
        }
        $codigo = (int) substr($linea, 0, 3);
        if (!in_array($codigo, $codigosOk, true)) {
            throw new RuntimeException(trim(sprintf(_('SMTP respondió %d: %s'), $codigo, $linea)));
        }
        while (isset($linea[3]) && $linea[3] === '-') {
            $linea = fgets($socket);
            if ($linea === false) {
                break;
            }
        }
    }
}
