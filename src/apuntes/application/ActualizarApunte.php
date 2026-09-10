<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use PDO;
use src\asientos\domain\value_objects\FilaApunteExcel;
use src\asientos\domain\contracts\AsientoRepository;
use Throwable;

final class ActualizarApunte
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CrearApunte $crear,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<FilaApunteExcel>
     */
    public function ejecutar(int $id, array $datos): array
    {
        $asiento = $this->asientos->porId($id);
        if ($asiento === null || $asiento->libro === 'X') {
            throw new InvalidArgumentException('Apunte no encontrado');
        }
        if ($asiento->origen === 'remesa' || $asiento->tipo === 'remesa' || $asiento->remesaId !== null) {
            throw new InvalidArgumentException('Un asiento de remesa no se edita a mano; se corrige reenviando');
        }
        if ($asiento->tipo === 'cierre') {
            $datos['es_cierre'] = true;
        }

        $this->pdo->beginTransaction();
        try {
            $this->asientos->borrar($id);
            $filas = $this->crear->ejecutar($datos);
            $this->pdo->commit();

            return $filas;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
