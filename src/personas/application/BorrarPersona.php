<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use PDO;
use PDOException;
use src\personas\domain\contracts\PersonaRepository;

final class BorrarPersona
{
    public function __construct(
        private readonly PersonaRepository $repo,
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

        $this->repo->desactivar($id);

        return [
            'eliminada' => false,
            'mensaje' => _("Tiene apuntes, remesas u otros datos; se ha dado de baja y ya no sale en el listado, pero se conserva el histórico."),
        ];
    }

    private function esViolacionClaveAjena(PDOException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';

        return $sqlState === '23503';
    }
}
