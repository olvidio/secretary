<?php

declare(strict_types=1);

namespace src\personas\application;

use InvalidArgumentException;
use PDO;
use PDOException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\ambito\application\AsegurarCuentaCorrientePersona;
use src\ambito\domain\contracts\CentroRepository;
use src\ambito\domain\entity\Centro;
use src\personal\application\AsegurarPlanPersonal;
use src\personas\domain\contracts\PersonaRepository;
use src\personas\domain\contracts\SolicitudVinculoCentroRepository;
use src\personas\domain\entity\Persona;

final class AprobarSolicitudVinculoCentro
{
    public function __construct(
        private readonly SolicitudVinculoCentroRepository $solicitudes,
        private readonly PersonaRepository $personas,
        private readonly IdentidadRepository $identidades,
        private readonly CentroRepository $centros,
        private readonly AsegurarCuentaCorrientePersona $cuentaCorriente,
        private readonly AsegurarPlanPersonal $planPersonal,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $datos persona_id opcional (vincular existente); si falta, alta nueva
     * @return array<string, mixed>
     */
    public function ejecutar(int $centroId, int $solicitudId, int $resolvedBy, array $datos = []): array
    {
        $solicitud = $this->solicitudes->porId($solicitudId);
        if ($solicitud === null || $solicitud->centroId !== $centroId || !$solicitud->esPendiente()) {
            throw new InvalidArgumentException(_("Solicitud no encontrada o ya resuelta"));
        }

        $identidad = $this->identidades->porId($solicitud->identidadId);
        if ($identidad === null) {
            throw new InvalidArgumentException(_("Cuenta solicitante no encontrada"));
        }
        $centro = $this->centros->porId($centroId);
        if ($centro === null) {
            throw new InvalidArgumentException(_("Centro no encontrado"));
        }

        $personaId = isset($datos['persona_id']) && $datos['persona_id'] !== ''
            ? (int) $datos['persona_id']
            : null;
        $yaEnCentro = $this->personaActivaEnCentro($solicitud->identidadId, $centroId);
        if ($yaEnCentro !== null && $personaId !== null && $personaId !== $yaEnCentro) {
            throw new InvalidArgumentException(_("La identidad ya está vinculada a este centro"));
        }
        if ($yaEnCentro !== null) {
            $personaId = $yaEnCentro;
        }

        if ($personaId !== null) {
            $persona = $this->personas->porId($personaId);
            if ($persona === null || $persona->centroId !== $centroId) {
                throw new InvalidArgumentException(_("El nombre indicado no pertenece a este centro"));
            }
            $otra = $this->identidades->identidadDePersona($personaId);
            if ($otra !== null && $otra->id !== $solicitud->identidadId) {
                throw new InvalidArgumentException(_("Ese nombre ya tiene otra cuenta personal vinculada"));
            }
        }

        $this->pdo->beginTransaction();
        try {
            if ($personaId === null) {
                $persona = $this->crearPersona($identidad->nombre, $centro, $identidad->alias ?? 'usr');
                $personaId = $persona->id;
            }
            if ($personaId === null) {
                throw new InvalidArgumentException(_("No se pudo resolver la persona"));
            }

            $this->identidades->vincularPersona($solicitud->identidadId, $personaId, $solicitud->anio);
            if (trim($identidad->email) !== '') {
                $this->personas->guardarEmail($personaId, $identidad->email);
            }
            $this->solicitudes->marcarResuelta($solicitudId, 'aprobada', $personaId, $resolvedBy);

            $persona = $this->personas->porId($personaId);
            if ($persona !== null) {
                $this->cuentaCorriente->ejecutar($persona);
                if ($persona->centroId !== null && $persona->id !== null) {
                    $this->planPersonal->ejecutar($persona->centroId, $persona->id);
                }
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (str_contains($e->getMessage(), 'personas_email')) {
                throw new InvalidArgumentException(_("Ese correo ya está asignado a otro nombre"));
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $fila = $persona?->toArray() ?? [];
        $fila['centro_id'] = $centroId;
        $fila['centro_nombre'] = $centro->nombre;
        $fila['anio'] = $solicitud->anio;

        return $fila;
    }

    private function personaActivaEnCentro(int $identidadId, int $centroId): ?int
    {
        foreach ($this->identidades->personasDe($identidadId) as $personaId) {
            $persona = $this->personas->porId($personaId);
            if ($persona !== null && $persona->activo && $persona->centroId === $centroId) {
                return $personaId;
            }
        }

        return null;
    }

    private function crearPersona(string $nombre, Centro $centro, string $aliasBase): Persona
    {
        if ($centro->id === null) {
            throw new InvalidArgumentException(_("Centro sin identificador"));
        }
        $partes = preg_split('/\s+/', trim($nombre), 2) ?: [];
        $nom = $partes[0] ?? $nombre;
        $ape = $partes[1] ?? '';
        $persona = $this->personas->guardar(new Persona(
            null,
            $nom,
            $ape,
            $this->inicialesLibres($aliasBase, $centro->id),
            null,
            null,
            null,
            null,
            null,
            0,
            $centro->id,
            true,
            null,
            $centro->tipoCierre === 'vivienda',
        ));

        return $persona;
    }

    private function inicialesLibres(string $alias, int $centroId): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($alias)) ?? '';
        $base = substr($base, 0, 6);
        if ($base === '') {
            $base = 'usr';
        }
        $candidato = $base;
        $n = 1;
        while ($this->personas->porInicialesDeCentro($centroId, $candidato) !== null) {
            $suf = (string) $n;
            $candidato = substr($base, 0, max(1, 6 - strlen($suf))) . $suf;
            $n++;
            if ($n > 99) {
                throw new InvalidArgumentException(_("No se pudieron generar iniciales únicas"));
            }
        }

        return $candidato;
    }
}
