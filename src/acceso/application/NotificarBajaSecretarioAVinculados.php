<?php

declare(strict_types=1);

namespace src\acceso\application;

use src\shared\domain\contracts\EnviadorCorreo;

final class NotificarBajaSecretarioAVinculados
{
    public function __construct(private readonly EnviadorCorreo $correo)
    {
    }

    /**
     * @param list<string> $nombresCentro
     * @param list<array{identidad_id: int, email: string, nombre: string}> $destinatarios
     */
    public function ejecutar(array $nombresCentro, array $destinatarios): void
    {
        if ($destinatarios === []) {
            return;
        }
        $centros = implode(', ', $nombresCentro);
        $asunto = _('Aviso: cambio de secretaría en su centro');
        foreach ($destinatarios as $dest) {
            $nombre = trim($dest['nombre']) !== '' ? trim($dest['nombre']) : $dest['email'];
            $cuerpo = sprintf(
                _("Hola %s,\n\nLe informamos de que el acceso de secretaría del centro %s en Secretario ha sido dado de baja.\n\nSu nombre en el centro, su libro personal y las remesas que ya haya enviado no se borran. Si necesita algo del centro, contacte con la nueva secretaría.\n"),
                $nombre,
                $centros,
            );
            $this->correo->enviar($dest['email'], $asunto, $cuerpo);
        }
    }
}
