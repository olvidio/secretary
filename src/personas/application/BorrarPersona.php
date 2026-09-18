<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use PDO;
use PDOException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\entity\Persona;

final class BorrarPersona
{
    public function __construct(
        private readonly PersonaRepository $repo,
        private readonly IdentidadRepository $identidades,
        private readonly PDO $pdo,
    ) {
    }

    /** @return array{eliminada: bool, mensaje: string} */
    public function ejecutar(int $id, int $centroId): array
    {
        $persona = $this->repo->porId($id);
        if ($persona === null || $persona->centroId !== $centroId) {
            throw new InvalidArgumentException(_("Persona no encontrada en este centro"));
        }
        if (!$persona->activo) {
            throw new InvalidArgumentException(_("La persona ya está dada de baja"));
        }

        try {
            $this->pdo->beginTransaction();
            $this->desvincularCuentaPersonal($persona);
            $this->repo->borrar($id);
            $this->pdo->commit();

            return [
                'eliminada' => true,
                'mensaje' => _("Persona eliminada."),
            ];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (!$this->esViolacionClaveAjena($e)) {
                throw $e;
            }
        }

        $this->desvincularCuentaPersonal($persona);
        $this->repo->desactivar($id);

        return [
            'eliminada' => false,
            'mensaje' => _("Tiene apuntes, remesas u otros datos; se ha dado de baja y ya no sale en el listado, pero se conserva el histórico."),
        ];
    }

    private function desvincularCuentaPersonal(Persona $persona): void
    {
        if ($persona->id === null) {
            return;
        }
        $identidad = $this->identidades->identidadDePersona($persona->id);
        if ($identidad === null) {
            return;
        }
        if (
            $persona->email !== null
            && strtolower($persona->email) === strtolower($identidad->email)
        ) {
            $this->repo->guardarEmail($persona->id, null);
        }
        $this->identidades->desvincularPersona($persona->id);
    }

    private function esViolacionClaveAjena(PDOException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';

        return $sqlState === '23503';
    }
}
