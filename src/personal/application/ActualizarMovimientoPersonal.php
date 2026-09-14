<?php

declare(strict_types=1);

namespace src\personal\application;

use InvalidArgumentException;
use PDO;
use src\asientos\domain\contracts\AsientoRepository;
use src\asientos\domain\entity\Asiento;
use Throwable;

final class ActualizarMovimientoPersonal
{
    public function __construct(
        private readonly ResolverPersonaActual $ambito,
        private readonly AsientoRepository $asientos,
        private readonly RegistrarMovimientoPersonal $registrar,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return list<Asiento>
     */
    public function ejecutar(int $id, array $datos): array
    {
        $ctx = $this->ambito->ejecutar();
        $asiento = $this->asientos->porId($id);
        if ($asiento === null || $asiento->libro !== 'X' || $asiento->personaId !== $ctx->personaId) {
            throw new InvalidArgumentException('Movimiento no encontrado');
        }
        if ($asiento->tipo === 'remesa' || $asiento->origen === 'remesa' || $asiento->remesaId !== null) {
            throw new InvalidArgumentException('Un movimiento de remesa no se edita a mano');
        }

        $this->pdo->beginTransaction();
        try {
            $this->asientos->borrar($id);
            $guardados = $this->registrar->ejecutar($datos);
            $this->pdo->commit();

            return $guardados;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
