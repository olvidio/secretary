<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\acceso\application\AsegurarLibroPersonalIdentidad;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\CentroRepository;
use src\personas\application\DesvincularPersonaCentro;
use src\personas\domain\contracts\PersonaRepository;

final class AdminDesvincularPersonaUsuario
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
        private readonly CentroRepository $centros,
        private readonly DesvincularPersonaCentro $desvincular,
    ) {
    }

    public function ejecutar(int $identidadId, int $personaId, int $operadorId): void
    {
        if ($identidadId === $operadorId) {
            throw new InvalidArgumentException(_("No puede modificarse a sí mismo"));
        }
        if ($personaId <= 0) {
            throw new InvalidArgumentException(_("Indique la persona"));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Usuario no encontrado"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("No se puede modificar al administrador de plataforma"));
        }

        $persona = $this->personas->porId($personaId);
        if ($persona === null || $persona->centroId === null) {
            throw new InvalidArgumentException(_("Persona no encontrada"));
        }
        $centro = $this->centros->porId($persona->centroId);
        if ($centro !== null && $centro->tipo === AsegurarLibroPersonalIdentidad::TIPO_CENTRO) {
            throw new InvalidArgumentException(
                _('El libro personal (Mis cuentas) no se quita aquí; use Borrar cuenta si quiere eliminar la cuenta entera.')
            );
        }

        $this->desvincular->ejecutar($identidadId, $personaId);
    }
}
