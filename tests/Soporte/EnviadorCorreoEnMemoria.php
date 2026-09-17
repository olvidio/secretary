<?php

declare(strict_types=1);

namespace Tests\Soporte;

use src\shared\domain\contracts\EnviadorCorreo;

final class EnviadorCorreoEnMemoria implements EnviadorCorreo
{
    /** @var list<array{destinatario: string, asunto: string, cuerpo: string}> */
    public array $enviados = [];

    public function enviar(string $destinatario, string $asunto, string $cuerpoTexto): void
    {
        $this->enviados[] = [
            'destinatario' => $destinatario,
            'asunto' => $asunto,
            'cuerpo' => $cuerpoTexto,
        ];
    }
}
