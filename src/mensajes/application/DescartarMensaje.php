<?php

declare(strict_types=1);

namespace src\mensajes\application;

use InvalidArgumentException;
use src\disponible\application\CancelarInstruccionesDestinos7;
use src\mensajes\domain\contracts\MensajeRepository;

final class DescartarMensaje
{
    public function __construct(
        private readonly MensajeRepository $mensajes,
        private readonly CancelarInstruccionesDestinos7 $cancelarDestinos,
    ) {
    }

    public function ejecutar(int $identidadId, int $mensajeId): void
    {
        if ($mensajeId <= 0) {
            throw new InvalidArgumentException(_('Mensaje no válido'));
        }
        $fila = $this->mensajes->porId($identidadId, $mensajeId);
        if ($fila === null) {
            throw new InvalidArgumentException(_('Mensaje no encontrado'));
        }
        if ($fila['tipo'] === 'destinos_7') {
            if (preg_match('/^destinos:(\d+):(\d+)$/', $fila['clave'], $m) === 1) {
                $this->cancelarDestinos->ejecutar((int) $m[1], (int) $m[2]);
            }

            return;
        }
        if (!$this->mensajes->descartar($identidadId, $mensajeId)) {
            throw new InvalidArgumentException(_('Mensaje no encontrado'));
        }
    }
}
