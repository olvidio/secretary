<?php

declare(strict_types=1);

namespace src\administracion\application;

use InvalidArgumentException;
use src\acceso\domain\contracts\IdentidadRepository;

final class ResumenFusionIdentidadesLegacy
{
    public function __construct(private readonly IdentidadRepository $identidades)
    {
    }

    /** @return array<string, mixed> */
    public function ejecutar(string $email, int $identidadPrincipalId): array
    {
        $email = strtolower(trim($email));
        $ids = $this->identidades->idsActivasPorEmail($email);
        if (count($ids) < 2) {
            throw new InvalidArgumentException(_("No hay varias cuentas activas con ese correo"));
        }
        if (!in_array($identidadPrincipalId, $ids, true)) {
            throw new InvalidArgumentException(_("La cuenta principal no pertenece a ese correo"));
        }
        $principal = $this->identidades->porId($identidadPrincipalId);
        if ($principal === null) {
            throw new InvalidArgumentException(_("Cuenta principal no encontrada"));
        }
        $absorber = array_values(array_filter($ids, static fn (int $id) => $id !== $identidadPrincipalId));
        $lineasCentros = [];
        $centrosVistos = [];
        foreach ($this->identidades->centrosDe($identidadPrincipalId) as $v) {
            $centrosVistos[$v->centroId] = true;
            $lineasCentros[] = $v->nombre . ' (' . $v->codigo . ') — ' . $v->rol;
        }
        foreach ($absorber as $id) {
            foreach ($this->identidades->centrosDe($id) as $v) {
                if (isset($centrosVistos[$v->centroId])) {
                    $lineasCentros[] = _('(fusionar rol) ') . $v->nombre . ' (' . $v->codigo . ')';
                    continue;
                }
                $lineasCentros[] = $v->nombre . ' (' . $v->codigo . ') — ' . $v->rol;
                $centrosVistos[$v->centroId] = true;
            }
        }
        $aliasesAbsorbidos = [];
        foreach ($absorber as $id) {
            $i = $this->identidades->porId($id);
            if ($i !== null && $i->alias !== null && $i->alias !== '') {
                $aliasesAbsorbidos[] = $i->alias;
            }
        }

        return [
            'email' => $email,
            'identidad_principal_id' => $identidadPrincipalId,
            'alias_principal' => $principal->alias,
            'absorber_ids' => $absorber,
            'aliases_a_eliminar' => $aliasesAbsorbidos,
            'centros_resultantes' => $lineasCentros,
            'texto' => implode("\n", [
                sprintf(
                    _('Se conservará la cuenta «%s» (%s) como única identidad.'),
                    $principal->alias ?? (string) $identidadPrincipalId,
                    $email,
                ),
                sprintf(_('Se eliminarán %d cuenta(s) duplicada(s): %s'), count($absorber), implode(', ', $aliasesAbsorbidos) ?: '—'),
                _('Mandatos de centro y vínculos de persona pasan a la cuenta principal.'),
                _('Los datos contables de los centros no se modifican.'),
                _('Centros tras fusionar:'),
                ...($lineasCentros !== [] ? $lineasCentros : ['—']),
            ]),
            'puede_fusionar' => true,
        ];
    }
}
