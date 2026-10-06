<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use PDO;
use PDOException;
use src\acceso\domain\contracts\IdentidadRepository;
use src\acceso\domain\value_objects\RolCentro;

final class FusionarIdentidadesLegacy
{
    public function __construct(
        private readonly IdentidadRepository $identidades,
        private readonly PDO $pdo,
    ) {
    }

    /** @return array{identidad_principal_id: int, cuentas_fusionadas: int} */
    public function ejecutar(string $email, int $identidadPrincipalId, int $operadorId, bool $confirmar): array
    {
        if (!$confirmar) {
            throw new InvalidArgumentException(_("Hay que confirmar la fusión"));
        }
        if ($operadorId > 0 && $identidadPrincipalId === $operadorId) {
            throw new InvalidArgumentException(_("Elija otra cuenta principal; no puede fusionarse a sí mismo mientras opera"));
        }
        $email = strtolower(trim($email));
        $ids = $this->identidades->idsActivasPorEmail($email);
        if (count($ids) < 2) {
            throw new InvalidArgumentException(_("No hay varias cuentas activas con ese correo"));
        }
        if (!in_array($identidadPrincipalId, $ids, true)) {
            throw new InvalidArgumentException(_("La cuenta principal no pertenece a ese correo"));
        }
        $principal = $this->identidades->porId($identidadPrincipalId);
        if ($principal === null || $principal->esAdmin) {
            throw new InvalidArgumentException(_("Cuenta principal no válida"));
        }
        $absorber = array_values(array_filter($ids, static fn (int $id) => $id !== $identidadPrincipalId));

        $this->pdo->beginTransaction();
        try {
            foreach ($absorber as $secundariaId) {
                if ($operadorId > 0 && $secundariaId === $operadorId) {
                    throw new InvalidArgumentException(_("No puede absorber la cuenta con la que está operando"));
                }
                $this->fusionarMandatos($identidadPrincipalId, $secundariaId);
                $this->fusionarPersonas($identidadPrincipalId, $secundariaId);
                $this->reassignReferencias($identidadPrincipalId, $secundariaId);
                $this->fusionarTotp($identidadPrincipalId, $secundariaId);
                $this->identidades->eliminarRespaldoCentrosBaja($secundariaId);
                $this->identidades->eliminar($secundariaId);
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new InvalidArgumentException(
                _('No se pudo completar la fusión (¿solicitudes o vínculos en conflicto?). ') . $e->getMessage(),
                0,
                $e,
            );
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'identidad_principal_id' => $identidadPrincipalId,
            'cuentas_fusionadas' => count($absorber),
        ];
    }

    private function fusionarMandatos(int $principalId, int $secundariaId): void
    {
        foreach ($this->identidades->centrosDe($secundariaId) as $v) {
            $rolActual = $this->identidades->rolEnCentro($principalId, $v->centroId);
            $rol = $this->rolPreferido($rolActual, $v->rol);
            $this->identidades->vincularCentro($principalId, $v->centroId, $rol);
        }
    }

    private function fusionarPersonas(int $principalId, int $secundariaId): void
    {
        $st = $this->pdo->prepare(
            'SELECT persona_id, anio FROM identidad_persona WHERE identidad_id = :s'
        );
        $st->execute([':s' => $secundariaId]);
        foreach ($st->fetchAll() as $row) {
            $personaId = (int) $row['persona_id'];
            $anio = isset($row['anio']) && $row['anio'] !== null ? (int) $row['anio'] : null;
            $vinculadas = $this->identidades->personasDe($principalId);
            if (in_array($personaId, $vinculadas, true)) {
                continue;
            }
            $this->identidades->vincularPersona($principalId, $personaId, $anio);
        }
    }

    private function reassignReferencias(int $principalId, int $secundariaId): void
    {
        $this->pdo->prepare(
            'DELETE FROM solicitudes_vinculo_centro s
             WHERE s.identidad_id = :s AND s.estado = \'pendiente\'
               AND EXISTS (
                   SELECT 1 FROM solicitudes_vinculo_centro p
                   WHERE p.identidad_id = :p AND p.centro_id = s.centro_id
                     AND p.anio = s.anio AND p.estado = \'pendiente\'
               )'
        )->execute([':s' => $secundariaId, ':p' => $principalId]);

        $this->pdo->prepare(
            'UPDATE solicitudes_vinculo_centro SET identidad_id = :p WHERE identidad_id = :s'
        )->execute([':p' => $principalId, ':s' => $secundariaId]);

        $this->pdo->prepare(
            'UPDATE remesa_solicitudes_detalle SET solicitada_por = :p WHERE solicitada_por = :s'
        )->execute([':p' => $principalId, ':s' => $secundariaId]);

        $this->pdo->prepare(
            'UPDATE solicitudes_vinculo_centro SET resolved_by = :p WHERE resolved_by = :s'
        )->execute([':p' => $principalId, ':s' => $secundariaId]);

        $this->pdo->prepare(
            'UPDATE aceptaciones_legales SET identidad_id = :p WHERE identidad_id = :s'
        )->execute([':p' => $principalId, ':s' => $secundariaId]);

        $this->pdo->prepare(
            'UPDATE ayuda_consultas SET identidad_id = :p WHERE identidad_id = :s'
        )->execute([':p' => $principalId, ':s' => $secundariaId]);
    }

    private function fusionarTotp(int $principalId, int $secundariaId): void
    {
        if ($this->identidades->totpConfirmado($principalId)) {
            return;
        }
        $st = $this->pdo->prepare(
            'SELECT secret_cifrado, confirmado_at FROM identidad_totp WHERE identidad_id = :s'
        );
        $st->execute([':s' => $secundariaId]);
        $row = $st->fetch();
        if (!is_array($row) || empty($row['secret_cifrado'])) {
            return;
        }
        $this->pdo->prepare('DELETE FROM identidad_totp WHERE identidad_id = :p OR identidad_id = :s')
            ->execute([':p' => $principalId, ':s' => $secundariaId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO identidad_totp (identidad_id, secret_cifrado, confirmado_at)
             VALUES (:p, :sec, :conf)'
        );
        $ins->execute([
            ':p' => $principalId,
            ':sec' => (string) $row['secret_cifrado'],
            ':conf' => $row['confirmado_at'],
        ]);
    }

    private function rolPreferido(?string $actual, string $nuevo): string
    {
        if (RolCentro::puedeEscribir($actual) || RolCentro::puedeEscribir($nuevo)) {
            return RolCentro::ADMIN;
        }

        return RolCentro::CONSULTA;
    }
}
