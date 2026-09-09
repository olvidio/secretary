<?php

declare(strict_types=1);

namespace src\ambito\application;

use InvalidArgumentException;
use PDO;
use src\ambito\domain\contracts\EjercicioRepository;
use src\asientos\domain\contracts\AsientoRepository;

/**
 * Borra el libro diario de un centro para recargar el Excel (pruebas, Fase 9).
 * Conserva el centro, usuarios, cuentas y nombres: la reimportación hace upsert.
 */
final class VaciarDatosCentro
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly EjercicioRepository $ejercicios,
        private readonly AsientoRepository $asientos,
    ) {
    }

    /**
     * @return array{ejercicios: int, asientos: int}
     */
    public function ejecutar(int $centroId, bool $confirmar): array
    {
        if (!$confirmar) {
            throw new InvalidArgumentException('Hay que confirmar el vaciado');
        }
        if ($centroId <= 0) {
            throw new InvalidArgumentException('Centro no válido');
        }

        $ids = [];
        foreach ($this->ejercicios->listarDeCentro($centroId) as $ejercicio) {
            if ($ejercicio->id !== null) {
                $ids[] = $ejercicio->id;
            }
        }

        $borrados = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($ids as $ejercicioId) {
                $st = $this->pdo->prepare('SELECT COUNT(*) FROM asientos WHERE ejercicio_id = :ej');
                $st->execute([':ej' => $ejercicioId]);
                $borrados += (int) $st->fetchColumn();
                $this->desvincularAsientos($ejercicioId);
                $this->borrarRemesas($ejercicioId);
                $this->borrarImportacion($ejercicioId);
                $this->borrarArqueosDeEjercicio($ejercicioId);
                $this->asientos->borrarPorEjercicio($ejercicioId);
            }
            $this->borrarArqueosDeFisicas($centroId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'ejercicios' => count($ids),
            'asientos' => $borrados,
        ];
    }

    private function desvincularAsientos(int $ejercicioId): void
    {
        $st = $this->pdo->prepare('UPDATE asientos SET remesa_id = NULL WHERE ejercicio_id = :ej');
        $st->execute([':ej' => $ejercicioId]);
        $st = $this->pdo->prepare('UPDATE asientos SET asiento_par_id = NULL WHERE ejercicio_id = :ej');
        $st->execute([':ej' => $ejercicioId]);
        $st = $this->pdo->prepare(
            'UPDATE asientos SET asiento_par_id = NULL
             WHERE asiento_par_id IN (SELECT id FROM asientos WHERE ejercicio_id = :ej)'
        );
        $st->execute([':ej' => $ejercicioId]);
    }

    private function borrarRemesas(int $ejercicioId): void
    {
        $st = $this->pdo->prepare('DELETE FROM remesas WHERE ejercicio_id = :ej');
        $st->execute([':ej' => $ejercicioId]);
    }

    private function borrarImportacion(int $ejercicioId): void
    {
        $st = $this->pdo->prepare('DELETE FROM import_filas WHERE ejercicio_id = :ej');
        $st->execute([':ej' => $ejercicioId]);
        $st = $this->pdo->prepare('DELETE FROM import_ejecuciones WHERE ejercicio_id = :ej');
        $st->execute([':ej' => $ejercicioId]);
    }

    private function borrarArqueosDeEjercicio(int $ejercicioId): void
    {
        $st = $this->pdo->prepare('DELETE FROM arqueos WHERE ejercicio_id = :ej');
        $st->execute([':ej' => $ejercicioId]);
    }

    private function borrarArqueosDeFisicas(int $centroId): void
    {
        $st = $this->pdo->prepare(
            'DELETE FROM arqueos WHERE cuenta_fisica_id IN (
                SELECT id FROM cuentas_fisicas WHERE centro_id = :c
            )'
        );
        $st->execute([':c' => $centroId]);
    }
}
