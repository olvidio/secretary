<?php

declare(strict_types=1);

namespace src\personas\application;

use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\entity\Centro;

/** Texto del centro en listados de cuentas personales (p. ej. solicitar acceso). */
final class EtiquetaCentroParaPersona
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    public function ejecutar(Centro $centro): string
    {
        $nombre = trim($centro->nombre);
        $codigo = trim($centro->codigo);
        if ($centro->id === null) {
            return $nombre !== '' ? $nombre : $codigo;
        }
        if ($nombre !== '' && !$this->nombrePareceSecretario($centro->id, $nombre)) {
            return $nombre;
        }

        return $this->tituloDesdeCodigo($codigo);
    }

    private function nombrePareceSecretario(int $centroId, string $nombreCentro): bool
    {
        foreach ($this->identidades->usuariosDeCentro($centroId) as $usuario) {
            if (strcasecmp(trim((string) ($usuario['nombre'] ?? '')), $nombreCentro) === 0) {
                return true;
            }
        }

        return false;
    }

    private function tituloDesdeCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        $legible = str_replace(['-', '_'], ' ', $codigo);

        return mb_convert_case($legible, MB_CASE_TITLE, 'UTF-8');
    }
}
