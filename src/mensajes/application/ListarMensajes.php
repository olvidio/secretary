<?php

declare(strict_types=1);

namespace src\mensajes\application;

use src\mensajes\domain\contracts\MensajeRepository;

final class ListarMensajes
{
    public function __construct(
        private readonly SincronizarBandeja $sincronizar,
        private readonly MensajeRepository $mensajes,
    ) {
    }

    /**
     * @return list<array{id:int, tipo:string, titulo:string, cuerpo:string, href:?string, accion:?string, leido:bool, fecha:string}>
     */
    public function ejecutar(int $identidadId): array
    {
        $this->sincronizar->ejecutar($identidadId);
        $out = [];
        foreach ($this->mensajes->listarAbiertos($identidadId) as $row) {
            $out[] = PresentadorMensaje::presentar($row);
        }

        return $out;
    }
}
