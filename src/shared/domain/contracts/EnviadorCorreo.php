<?php

declare(strict_types=1);

namespace src\shared\domain\contracts;

interface EnviadorCorreo
{
    public function enviar(string $destinatario, string $asunto, string $cuerpoTexto): void;
}
