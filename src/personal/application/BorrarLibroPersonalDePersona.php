<?php

declare(strict_types=1);

namespace src\personal\application;

use PDO;
use src\personal\infrastructure\persistence\AlmacenCopiasPersonal;
use src\personal\infrastructure\persistence\RutasCopiasPersonal;
use src\personas\domain\contracts\PersonaRepository;

/**
 * Elimina movimientos, importaciones bancarias y cuentas del libro X de una persona.
 * No borra la fila de personas ni remesas ya enviadas al centro.
 */
final class BorrarLibroPersonalDePersona
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PersonaRepository $personas,
    ) {
    }

    public function ejecutar(int $personaId, int $centroId, bool $borrarCopiasEnDisco = true): void
    {
        $this->borrarRemesasBorrador($personaId);
        $this->borrarMovimientos($personaId);
        $this->pdo->prepare('DELETE FROM banco_import_filas WHERE persona_id = :p')
            ->execute([':p' => $personaId]);
        $this->pdo->prepare('DELETE FROM personal_cierre_mes WHERE persona_id = :p')
            ->execute([':p' => $personaId]);
        $this->pdo->prepare(
            'DELETE FROM prevision_personal_lineas WHERE persona_id = :p'
        )->execute([':p' => $personaId]);
        $this->pdo->prepare(
            'UPDATE personas SET banco_csv = NULL, dia_cierre = NULL, cierre_dia_habil = FALSE WHERE id = :p'
        )->execute([':p' => $personaId]);
        $this->pdo->prepare(
            'UPDATE cuentas SET padre_id = NULL WHERE centro_id = :c AND persona_id = :p AND libro = \'X\''
        )->execute([':c' => $centroId, ':p' => $personaId]);
        $this->pdo->prepare(
            'DELETE FROM cuentas WHERE centro_id = :c AND persona_id = :p AND libro = \'X\''
        )->execute([':c' => $centroId, ':p' => $personaId]);

        if ($borrarCopiasEnDisco) {
            $this->borrarCopias($personaId);
        }
    }

    private function borrarRemesasBorrador(int $personaId): void
    {
        $st = $this->pdo->prepare(
            'SELECT id FROM remesas WHERE persona_id = :p AND estado = \'borrador\''
        );
        $st->execute([':p' => $personaId]);
        $ids = array_map(static fn ($row) => (int) $row['id'], $st->fetchAll());
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo->prepare(
            "UPDATE asientos SET remesa_id = NULL WHERE remesa_id IN ($placeholders)"
        )->execute($ids);
        $this->pdo->prepare(
            "DELETE FROM remesas WHERE id IN ($placeholders)"
        )->execute($ids);
    }

    private function borrarMovimientos(int $personaId): void
    {
        $this->pdo->prepare(
            'UPDATE asientos SET asiento_par_id = NULL
             WHERE libro = \'X\' AND persona_id = :p'
        )->execute([':p' => $personaId]);
        $this->pdo->prepare(
            'DELETE FROM asientos WHERE libro = \'X\' AND persona_id = :p'
        )->execute([':p' => $personaId]);
    }

    private function borrarCopias(int $personaId): void
    {
        $persona = $this->personas->porId($personaId);
        if ($persona === null) {
            return;
        }
        $almacen = new AlmacenCopiasPersonal(
            RutasCopiasPersonal::directorio(),
            $personaId,
            $persona->iniciales,
        );
        foreach ($almacen->listar() as $copia) {
            try {
                $almacen->borrarPorNombre($copia['filename']);
            } catch (\Throwable) {
                // Si el fichero ya no está, seguimos con el borrado de cuenta.
            }
        }
    }
}
