<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use PDO;
use PDOException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\value_objects\RolCentro;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\services\TipoEntidad;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\domain\contracts\PersonaRepository;

/**
 * El secretario enlaza su propia cuenta personal con un nombre ya existente de ese centro,
 * sin pasar por la bandeja de solicitudes.
 */
final class VincularNombrePropio
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PersonaRepository $personas,
        private readonly CentroRepository $centros,
        private readonly AsegurarCuentaCorrientePersona $cuentaCorriente,
        private readonly AsegurarPlanPersonal $planPersonal,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function ejecutar(int $identidadId, array $datos): array
    {
        $centroId = (int) ($datos['centro_id'] ?? 0);
        $personaId = (int) ($datos['persona_id'] ?? 0);
        $anio = (int) ($datos['anio'] ?? 0);
        if ($centroId <= 0 || $personaId <= 0) {
            throw new InvalidArgumentException(_("Indique el centro y el nombre"));
        }
        if ($anio < 2000 || $anio > 2100) {
            throw new InvalidArgumentException(_("Indique el año del ejercicio"));
        }
        $rol = $this->identidades->rolEnCentro($identidadId, $centroId);
        if ($rol === null || !RolCentro::puedeEscribir($rol)) {
            throw new InvalidArgumentException(_("Solo el secretario de ese centro puede vincular su nombre directamente"));
        }
        if ($this->identidades->tienePersonaEnAlgunCentro($identidadId)) {
            throw new InvalidArgumentException(_("Ya tiene un centro vinculado. Desvincúlese antes de solicitar otro."));
        }
        $centro = $this->centros->porId($centroId);
        if ($centro === null || !$centro->activo || !TipoEntidad::esCentroParaPersona($centro->tipo)) {
            throw new InvalidArgumentException(_("Solo puede vincularse un nombre de un centro n"));
        }
        $persona = $this->personas->porId($personaId);
        if ($persona === null || !$persona->activo || $persona->centroId !== $centroId) {
            throw new InvalidArgumentException(_("El nombre indicado no pertenece a este centro"));
        }
        $otra = $this->identidades->identidadDePersona($personaId);
        if ($otra !== null && $otra->id !== $identidadId) {
            throw new InvalidArgumentException(_("Ese nombre ya tiene otra cuenta personal vinculada"));
        }
        $identidad = $this->identidades->porId($identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Cuenta no encontrada"));
        }
        $email = strtolower(trim($identidad->email));
        if ($email !== '') {
            $conEseCorreo = $this->personas->porEmailEnCentro($centroId, $email);
            if ($conEseCorreo !== null && $conEseCorreo->id !== $personaId) {
                throw new InvalidArgumentException(_("Ese correo ya está asignado a otro nombre"));
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->identidades->vincularPersona($identidadId, $personaId, $anio);
            if ($email !== '') {
                $this->personas->guardarEmail($personaId, $email);
            }
            $this->cuentaCorriente->ejecutar($persona);
            $libro = $this->identidades->personaLibroPersonalDe($identidadId);
            if (($libro === null || $libro === $personaId) && $persona->id !== null) {
                $this->planPersonal->ejecutar($centroId, $persona->id);
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (str_contains($e->getMessage(), 'personas_email')) {
                throw new InvalidArgumentException(_("Ese correo ya está asignado a otro nombre"), 0, $e);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'persona_id' => $personaId,
            'iniciales' => $persona->iniciales,
            'nombre' => $persona->nombreCompleto(),
            'centro_id' => $centroId,
            'centro_nombre' => $centro->nombre,
            'anio' => $anio,
        ];
    }
}
