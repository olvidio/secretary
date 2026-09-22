<?php

declare(strict_types=1);

namespace src\administracion\application;

use PDO;
use src\acceso\domain\contracts\IdentidadRepository;

/** Borra credenciales y vínculos de acceso; no toca datos contables del centro. */
final class PurgarIdentidadAcceso
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PDO $pdo,
    ) {
    }

    public function ejecutar(int $identidadId): void
    {
        $this->pdo->prepare(
            'DELETE FROM remesa_solicitudes_detalle WHERE solicitada_por = :id'
        )->execute([':id' => $identidadId]);
        $this->pdo->prepare(
            'UPDATE solicitudes_vinculo_centro SET resolved_by = NULL WHERE resolved_by = :id'
        )->execute([':id' => $identidadId]);
        $this->identidades->eliminarRespaldoCentrosBaja($identidadId);
        $this->identidades->eliminar($identidadId);
    }
}
