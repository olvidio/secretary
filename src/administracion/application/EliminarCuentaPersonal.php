<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use PDO;
use src\acceso\application\AsegurarLibroPersonalIdentidad;
use src\acceso\application\NotificarCancelacionCuentaPersonal;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\domain\contracts\CentroRepository;
use src\personal\application\BorrarLibroPersonalDePersona;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;

final class EliminarCuentaPersonal
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
        private readonly CentroRepository $centros,
        private readonly BorrarLibroPersonalDePersona $borrarLibro,
        private readonly EliminarCentro $eliminarCentro,
        private readonly NotificarCancelacionCuentaPersonal $notificar,
        private readonly PDO $pdo,
    ) {
    }

    public function ejecutar(int $identidadId, bool $confirmar, bool $enviarCorreoCancelacion = true): void
    {
        if (!$confirmar) {
            throw new InvalidArgumentException(_("Hay que confirmar el borrado del usuario"));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Usuario no encontrado"));
        }
        if ($identidad->esAdmin) {
            throw new InvalidArgumentException(_("No se puede eliminar al administrador de plataforma"));
        }
        if (!$this->identidades->esCuentaPersonal($identidadId)) {
            throw new InvalidArgumentException(
                _('Las cuentas de secretario de centro se eliminarán con otra herramienta; por ahora no se pueden borrar desde aquí.')
            );
        }

        $email = $identidad->email;
        $nombre = $identidad->nombre !== '' ? $identidad->nombre : ($identidad->alias ?? $email);
        $centrosTipoP = [];
        $personasReales = [];

        foreach ($this->identidades->personasDe($identidadId) as $personaId) {
            $persona = $this->personas->porId($personaId);
            if ($persona === null || $persona->centroId === null || $persona->id === null) {
                continue;
            }
            $centro = $this->centros->porId($persona->centroId);
            if ($centro === null || $centro->id === null) {
                continue;
            }
            if ($centro->tipo === AsegurarLibroPersonalIdentidad::TIPO_CENTRO) {
                $centrosTipoP[$centro->id] = $persona->id;
            } else {
                $personasReales[] = $persona;
            }
        }

        foreach ($personasReales as $persona) {
            $this->borrarLibro->ejecutar($persona->id, $persona->centroId);
            $this->desvincularPersonaCentro($persona, $email);
        }

        $this->pdo->prepare(
            'DELETE FROM remesa_solicitudes_detalle WHERE solicitada_por = :id'
        )->execute([':id' => $identidadId]);
        $this->pdo->prepare(
            'UPDATE solicitudes_vinculo_centro SET resolved_by = NULL WHERE resolved_by = :id'
        )->execute([':id' => $identidadId]);

        foreach ($centrosTipoP as $centroId => $personaId) {
            $this->borrarLibro->ejecutar($personaId, $centroId);
            $this->eliminarCentro->ejecutar($centroId, true);
        }

        $this->identidades->eliminar($identidadId);

        if ($enviarCorreoCancelacion) {
            $this->notificar->ejecutar($email, $nombre);
        }
    }

    private function desvincularPersonaCentro(Persona $persona, string $emailIdentidad): void
    {
        if ($persona->id === null) {
            return;
        }
        if (
            $persona->email !== null
            && strtolower($persona->email) === strtolower($emailIdentidad)
        ) {
            $this->personas->guardarEmail($persona->id, null);
        }
        $this->identidades->desvincularPersona($persona->id);
    }
}
