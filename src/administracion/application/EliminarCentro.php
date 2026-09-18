<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use PDO;
use src\ambito\application\VaciarDatosCentro;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\contracts\EjercicioRepository;

final class EliminarCentro
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly CentroRepository $centros,
        private readonly EjercicioRepository $ejercicios,
        private readonly VaciarDatosCentro $vaciarDatos,
    ) {
    }

    public function ejecutar(int $centroId, bool $confirmar): void
    {
        if (!$confirmar) {
            throw new InvalidArgumentException(_("Hay que confirmar el borrado del centro"));
        }
        if ($this->centros->porId($centroId) === null) {
            throw new InvalidArgumentException(_("Centro no encontrado"));
        }

        $this->vaciarDatos->ejecutar($centroId, true);
        $this->pdo->beginTransaction();
        try {
            foreach ($this->ejercicios->listarDeCentro($centroId) as $ejercicio) {
                if ($ejercicio->id === null) {
                    continue;
                }
                $this->pdo->prepare('DELETE FROM informes_613_mes WHERE ejercicio_id = :e')
                    ->execute([':e' => $ejercicio->id]);
            }
            $this->pdo->prepare(
                'UPDATE ejercicios SET ejercicio_anterior_id = NULL WHERE centro_id = :c'
            )->execute([':c' => $centroId]);
            $this->pdo->prepare('DELETE FROM remesas WHERE centro_id = :c')->execute([':c' => $centroId]);
            $this->pdo->prepare('DELETE FROM import_ejecuciones WHERE centro_id = :c')->execute([':c' => $centroId]);
            $this->pdo->prepare('DELETE FROM envios_dl WHERE centro_id = :c')->execute([':c' => $centroId]);
            $this->pdo->prepare('DELETE FROM asignaciones_labores WHERE centro_id = :c')->execute([':c' => $centroId]);
            $this->pdo->prepare('DELETE FROM ejercicios WHERE centro_id = :c')->execute([':c' => $centroId]);
            $this->pdo->prepare(
                'DELETE FROM identidad_persona WHERE persona_id IN (SELECT id FROM personas WHERE centro_id = :c)'
            )->execute([':c' => $centroId]);
            $this->pdo->prepare('UPDATE cuentas SET padre_id = NULL WHERE centro_id = :c')
                ->execute([':c' => $centroId]);
            $this->pdo->prepare('DELETE FROM cuentas WHERE centro_id = :c')->execute([':c' => $centroId]);
            $this->pdo->prepare('DELETE FROM personas WHERE centro_id = :c')->execute([':c' => $centroId]);
            $this->pdo->prepare('DELETE FROM cuentas_fisicas WHERE centro_id = :c')->execute([':c' => $centroId]);
            $this->pdo->prepare('DELETE FROM identidad_centro WHERE centro_id = :c')->execute([':c' => $centroId]);
            $this->centros->borrar($centroId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
