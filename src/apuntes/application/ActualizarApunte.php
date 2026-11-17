<?php

declare(strict_types=1);

namespace src\apuntes\application;

use InvalidArgumentException;
use PDO;
use src\apuntes\domain\contracts\BancoCentroImportRepository;
use src\asientos\domain\value_objects\FilaApunteExcel;
use src\asientos\domain\contracts\AsientoRepository;
use Throwable;

final class ActualizarApunte
{
    public function __construct(
        private readonly AsientoRepository $asientos,
        private readonly CrearApunte $crear,
        private readonly PDO $pdo,
        private readonly BancoCentroImportRepository $bancoImport,
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
            throw new InvalidArgumentException(_("Apunte no encontrado"));
        }
        if ($asiento->origen === 'remesa' || $asiento->tipo === 'remesa' || $asiento->remesaId !== null) {
            throw new InvalidArgumentException(_("Un asiento de remesa no se edita a mano; se corrige reenviando"));
        }
        if ($asiento->origen === 'asignacion' || $asiento->tipo === 'asignacion_cc') {
            throw new InvalidArgumentException(_("Un asiento de destinos 7 no se edita a mano; se corrige con una nueva propuesta"));
        }
        if ($asiento->tipo === 'cierre') {
            $datos['es_cierre'] = true;
        }

        $this->pdo->beginTransaction();
        try {
            // Editar es borrar y volver a crear: la huella del extracto (banco del
            // centro) se pasa al asiento nuevo para que reimportar no lo duplique.
            $filasBanco = $this->bancoImport->desligarDeAsiento($id);
            $this->asientos->borrar($id);
            $filas = $this->crear->ejecutar($datos);
            if ($filasBanco !== [] && isset($filas[0])) {
                $this->bancoImport->religarAsiento($filasBanco, $filas[0]->id);
            }
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
